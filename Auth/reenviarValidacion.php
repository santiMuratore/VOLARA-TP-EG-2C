<?php

require_once __DIR__ . '/../Includes/functions.php';
require_once __DIR__ . '/../Includes/functionsF2.php';
require_once __DIR__ . '/../Includes/mailer.php';

/*
 * Esta página es solo para visitantes: si ya hay una sesión iniciada
 * (por ejemplo, abierta desde otra pestaña), se redirige al inicio.
 */
if (!empty($_SESSION['codUsuario'])) {
    header('Location: ' . rtrim(BASE_URL, '/') . '/Pages/Publico/index.php');
    exit;
}


$mensaje = '';
$error = '';

$mail = trim($_GET['mail'] ?? '');


// ---------------------------------------------------------
// CSRF
// ---------------------------------------------------------

if (empty($_SESSION['csrf_reenvio_validacion'])) {
    $_SESSION['csrf_reenvio_validacion'] =
        bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_reenvio_validacion'];


// ---------------------------------------------------------
// Procesar formulario
// ---------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $mail = trim($_POST['mail'] ?? '');

    $csrfRecibido =
        $_POST['csrf_token'] ?? '';


    if (
        !hash_equals(
            $csrfToken,
            (string) $csrfRecibido
        )
    ) {

        $error =
            'El formulario venció. Recargá la página e intentá nuevamente.';

    } elseif (
        !filter_var(
            $mail,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $error =
            'Ingresá un correo electrónico válido.';

    } else {

        try {

            $conexion = conectarDB();


            // ---------------------------------------------
            // Buscar solamente usuarios pendientes
            // ---------------------------------------------

            $sql = "
                SELECT
                    codUsuario,
                    nombre,
                    mail
                FROM usuarios
                WHERE mail = :mail
                  AND tipoUsuario = 'usuario'
                  AND estadoUsuario = 'pendiente de validación'
                LIMIT 1
            ";

            $consulta =
                $conexion->prepare($sql);

            $consulta->execute([
                'mail' => $mail
            ]);

            $usuario =
                $consulta->fetch(PDO::FETCH_ASSOC);


            if ($usuario) {

                // -----------------------------------------
                // Generar un token NUEVO
                // -----------------------------------------

                $nuevoToken =
                    bin2hex(random_bytes(32));


                $sql = "
                    UPDATE usuarios
                    SET tokenValidacion = :token
                    WHERE codUsuario = :codUsuario
                      AND estadoUsuario = 'pendiente de validación'
                ";

                $consulta =
                    $conexion->prepare($sql);

                $consulta->execute([
                    'token' => $nuevoToken,
                    'codUsuario' =>
                        $usuario['codUsuario']
                ]);


                // -----------------------------------------
                // Crear enlace
                // -----------------------------------------

                $enlaceValidacion =
                    rtrim(BASE_URL, '/')
                    . '/Auth/validarCuenta.php?token='
                    . rawurlencode($nuevoToken);


                $enlaceSeguro =
                    htmlspecialchars(
                        $enlaceValidacion,
                        ENT_QUOTES,
                        'UTF-8'
                    );

                $nombreSeguro =
                    htmlspecialchars(
                        $usuario['nombre'],
                        ENT_QUOTES,
                        'UTF-8'
                    );


                // -----------------------------------------
                // Correo
                // -----------------------------------------

                $cuerpo = plantillaValidacionVolara(
                    $usuario['nombre'],
                    $enlaceValidacion
                );


                try {

                    enviarMail(
                        $usuario['mail'],
                        'Nuevo enlace de validación - Volara',
                        $cuerpo
                    );

                } catch (Throwable $e) {

                    error_log(
                        'Error reenviando validación: '
                        . $e->getMessage()
                    );
                }
            }


            /*
             * IMPORTANTE:
             * mostramos el mismo mensaje exista
             * o no exista esa cuenta.
             */
            $mensaje =
                'Si existe una cuenta pendiente de validación asociada a ese correo, recibirás un nuevo enlace.';


            // Renovar token CSRF
            $_SESSION['csrf_reenvio_validacion'] =
                bin2hex(random_bytes(32));

            $csrfToken =
                $_SESSION['csrf_reenvio_validacion'];


        } catch (PDOException $e) {

            error_log(
                'Error reenviando validación: '
                . $e->getMessage()
            );

            $error =
                'No pudimos procesar la solicitud. Intentá nuevamente.';
        }
    }
}


// ---------------------------------------------------------
// Layout
// ---------------------------------------------------------

$titulo = 'Reenviar validación';

require_once __DIR__ . '/../Includes/header.php';
require_once __DIR__ . '/../Includes/navbar.php';

?>


<main class="section section-gray">

    <div class="container">

        <div class="row justify-content-center">

            <div class="col-md-8 col-lg-5">

                <div class="volara-card">

                    <div class="text-center mb-4">

                        <span class="eyebrow">
                            Validación de cuenta
                        </span>

                        <h1 class="section-title mt-2 mb-2">
                            Reenviar correo
                        </h1>

                        <p class="text-secondary mb-0">
                            Ingresá el correo con el que
                            te registraste en Volara.
                        </p>

                    </div>


                    <?php if ($mensaje !== ''): ?>

                        <div
                            class="volara-alert alert-success"
                            role="status"
                        >

                            <i
                                class="bi bi-check-circle-fill"
                                aria-hidden="true"
                            ></i>

                            <span>
                                <?= htmlspecialchars(
                                    $mensaje,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </span>

                        </div>

                    <?php endif; ?>


                    <?php if ($error !== ''): ?>

                        <div
                            class="volara-alert alert-danger"
                            role="alert"
                        >

                            <i
                                class="bi bi-exclamation-circle-fill"
                                aria-hidden="true"
                            ></i>

                            <span>
                                <?= htmlspecialchars(
                                    $error,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </span>

                        </div>

                    <?php endif; ?>


                    <form method="post">
                        <p class="required-help">
                            Los campos marcados con
                            <span class="required-mark" aria-hidden="true">*</span>
                            son obligatorios.
                        </p>

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars(
                                $csrfToken,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >


                        <div class="form-group">

                            <label
                                for="mail"
                                class="volara-label"
                            >
                                Email
                                <span class="required-mark" aria-hidden="true">*</span>
                            </label>

                            <input
                                type="email"
                                id="mail"
                                name="mail"
                                class="volara-input"
                                value="<?= htmlspecialchars(
                                    $mail,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                placeholder="nombre@correo.com"
                                required
                            >

                        </div>


                        <button
                            type="submit"
                            class="btn btn-volara btn-volara-lg w-100"
                        >

                            <i
                                class="bi bi-envelope-arrow-up me-1"
                                aria-hidden="true"
                            ></i>

                            Reenviar enlace

                        </button>


                        <p class="text-center mt-4 mb-0">

                            <a
                                href="<?= BASE_URL ?>/Auth/login.php"
                                class="fw-semibold"
                            >
                                Volver a iniciar sesión
                            </a>

                        </p>

                    </form>

                </div>

            </div>

        </div>

    </div>

</main>


<?php
require_once __DIR__ . '/../Includes/footer.php';
?>