<?php

require_once __DIR__ . '/../Includes/functions.php';

$error = '';
$mensaje = '';

$token = $_GET['token'] ?? $_POST['token'] ?? '';

$tokenValido = false;

if (preg_match('/^[a-f0-9]{64}$/', $token)) {

    $conexion = conectarDB();

    $sql = "
        SELECT codUsuario
        FROM usuarios
        WHERE tokenRecuperacion = :token
          AND vencimientoToken IS NOT NULL
          AND vencimientoToken > NOW()
    ";

    $consulta = $conexion->prepare($sql);

    $consulta->execute([
        'token' => $token
    ]);

    $usuario = $consulta->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        $tokenValido = true;
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $clave = $_POST['clave'] ?? '';
    $confirmarClave = $_POST['confirmarClave'] ?? '';

    if (!$tokenValido) {

        $error = 'El enlace de recuperación no es válido o ha vencido.';

    } elseif ($clave === '') {

        $error = 'La nueva contraseña es obligatoria.';

    } elseif ($clave !== $confirmarClave) {

        $error = 'Las contraseñas no coinciden.';

    } else {

        $claveHash = password_hash(
            $clave,
            PASSWORD_DEFAULT
        );

        $sql = "
            UPDATE usuarios
            SET
                clave = :clave,
                tokenRecuperacion = NULL,
                vencimientoToken = NULL
            WHERE tokenRecuperacion = :token
              AND vencimientoToken > NOW()
        ";

        $consulta = $conexion->prepare($sql);

        $consulta->execute([
            'clave' => $claveHash,
            'token' => $token
        ]);

        if ($consulta->rowCount() > 0) {

            $mensaje = 'Tu contraseña fue actualizada correctamente.';

            $tokenValido = false;

        } else {

            $error = 'El enlace de recuperación no es válido o ha vencido.';
        }
    }
}

?>

<?php

$titulo = 'Restablecer contraseña';

require_once __DIR__ . '/../Includes/header.php';
require_once __DIR__ . '/../Includes/navbar.php';

?>

<main class="section section-gray">

    <div class="container">

        <div class="row justify-content-center">

            <div class="col-md-8 col-lg-5">

                <div class="volara-card">

                    <?php if ($mensaje !== ''): ?>

                        <div class="text-center">

                            <div class="mb-3">

                                <i
                                    class="bi bi-check-circle-fill text-success"
                                    style="font-size: 3rem;"
                                    aria-hidden="true"
                                ></i>

                            </div>

                            <span class="eyebrow">
                                Contraseña actualizada
                            </span>

                            <h1 class="section-title mt-2 mb-2">
                                ¡Todo listo!
                            </h1>

                            <p class="text-secondary mb-4">
                                <?= htmlspecialchars(
                                    $mensaje,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </p>

                            <a
                                href="<?= BASE_URL ?>/Auth/login.php"
                                class="btn-volara btn-volara-lg w-100"
                            >
                                <i
                                    class="bi bi-box-arrow-in-right"
                                    aria-hidden="true"
                                ></i>

                                Iniciar sesión
                            </a>

                        </div>


                    <?php elseif (!$tokenValido): ?>

                        <div class="text-center">

                            <div class="mb-3">

                                <i
                                    class="bi bi-exclamation-circle-fill text-danger"
                                    style="font-size: 3rem;"
                                    aria-hidden="true"
                                ></i>

                            </div>

                            <span class="eyebrow">
                                Recuperación de contraseña
                            </span>

                            <h1 class="section-title mt-2 mb-3">
                                Enlace no válido
                            </h1>

                            <div class="volara-alert alert-danger text-start">

                                <i
                                    class="bi bi-exclamation-triangle-fill"
                                    aria-hidden="true"
                                ></i>

                                <span>
                                    El enlace de recuperación no es válido
                                    o ha vencido.
                                </span>

                            </div>

                            <a
                                href="<?= BASE_URL ?>/Auth/recuperarClave.php"
                                class="btn-volara-outline w-100"
                            >
                                Solicitar un nuevo enlace
                            </a>

                        </div>


                    <?php else: ?>

                        <div class="text-center mb-4">

                            <span class="eyebrow">
                                Seguridad de tu cuenta
                            </span>

                            <h1 class="section-title mt-2 mb-2">
                                Restablecer contraseña
                            </h1>

                            <p class="text-secondary mb-0">
                                Elegí una nueva contraseña para tu cuenta.
                            </p>

                        </div>


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
                                name="token"
                                value="<?= htmlspecialchars(
                                    $token,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >


                            <div class="form-group">

                                <label
                                    for="clave"
                                    class="volara-label"
                                >
                                    Nueva contraseña
                                </label>

                                <input
                                    type="password"
                                    id="clave"
                                    name="clave"
                                    class="volara-input"
                                    placeholder="Ingresá tu nueva contraseña"
                                    autocomplete="new-password"
                                    required
                                >

                            </div>


                            <div class="form-group">

                                <label
                                    for="confirmarClave"
                                    class="volara-label"
                                >
                                    Confirmar nueva contraseña
                                </label>

                                <input
                                    type="password"
                                    id="confirmarClave"
                                    name="confirmarClave"
                                    class="volara-input"
                                    placeholder="Repetí tu nueva contraseña"
                                    autocomplete="new-password"
                                    required
                                >

                            </div>


                            <button
                                type="submit"
                                class="btn-volara btn-volara-lg w-100"
                            >

                                <i
                                    class="bi bi-key"
                                    aria-hidden="true"
                                ></i>

                                Cambiar contraseña

                            </button>

                        </form>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

</main>

<?php
require_once __DIR__ . '/../Includes/footer.php';
?>