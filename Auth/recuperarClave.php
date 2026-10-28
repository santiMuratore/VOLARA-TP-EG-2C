<?php

require_once __DIR__ . '/../Includes/functions.php';
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
$mail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $mail = trim($_POST['mail'] ?? '');

    $tokenRecibido =
        $_POST['csrf_token'] ?? null;

    if (
        !is_string($tokenRecibido) ||
        !csrfValido($tokenRecibido)
    ) {

        $error =
            'La solicitud no es válida. Recargá la página e intentá nuevamente.';

    } elseif (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {

        $error = 'Ingresá un correo válido.';

    } else {

        $conexion = conectarDB();

        $sql = "
            SELECT codUsuario, mail, nombre
            FROM usuarios
            WHERE mail = :mail
        ";

        $consulta = $conexion->prepare($sql);

        $consulta->execute([
            'mail' => $mail
        ]);

        $usuario = $consulta->fetch(PDO::FETCH_ASSOC);

        if ($usuario) {

             $nombre =
                (string) $usuario['nombre'];

            $tokenRecuperacion = bin2hex(random_bytes(32));

            $sql = "
                UPDATE usuarios
                SET
                    tokenRecuperacion = :tokenRecuperacion,
                    vencimientoToken = DATE_ADD(NOW(), INTERVAL 1 HOUR)
                WHERE codUsuario = :codUsuario
            ";

            $consulta = $conexion->prepare($sql);

            $consulta->execute([
                'tokenRecuperacion' => $tokenRecuperacion,
                'codUsuario' => $usuario['codUsuario']
            ]);

            $enlaceRecuperacion =
                rtrim(BASE_URL, '/')
                . '/Auth/restablecerClave.php?token='
                . rawurlencode($tokenRecuperacion);


            $nombreSeguro =
            htmlspecialchars(
                $nombre,
                ENT_QUOTES,
                'UTF-8'
            );
        

            $cuerpo = plantillaRecuperacionVolara(
                $nombre,
                $enlaceRecuperacion
            );

            enviarMail(
                $usuario['mail'],
                'Recuperá tu contraseña de Volara',
                $cuerpo
            );
        }
        $mensaje =
            'Si existe una cuenta asociada a ese correo, recibirás un enlace para recuperar tu contraseña.';
    }
}

?>

<?php

$titulo = 'Recuperar contraseña';

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
                            Seguridad de tu cuenta
                        </span>

                        <h1 class="section-title mt-2 mb-2">
                            Recuperar contraseña
                        </h1>

                        <p class="text-secondary mb-0">
                            Ingresá el correo asociado a tu cuenta.
                        </p>

                    </div>


                    <?php if ($mensaje !== ''): ?>

                        <div class="volara-alert alert-success">

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

                        <div class="volara-alert alert-danger">

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
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars(
                                csrfToken(),
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
                            class="btn-volara btn-volara-lg w-100"
                        >

                            <i
                                class="bi bi-envelope-arrow-up"
                                aria-hidden="true"
                            ></i>

                            Enviar enlace de recuperación

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