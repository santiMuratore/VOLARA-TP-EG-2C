<?php

require_once __DIR__ . '/../Includes/functions.php';

$mensaje = '';
$error = '';

$token = $_GET['token'] ?? '';


if ($token === '') {

    $error = 'El enlace de validación no es válido.';

} else {

    $conexion = conectarDB();

    $sql = "
        SELECT codUsuario, estadoUsuario
        FROM usuarios
        WHERE tokenValidacion = :token
    ";

    $consulta = $conexion->prepare($sql);

    $consulta->execute([
        'token' => $token
    ]);

    $usuario = $consulta->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {

        $error = 'El enlace de validación no existe o ya fue utilizado.';

    } else {
        if(isset($_SESSION['codUsuario'])) session_destroy();
        $sql = "
            UPDATE usuarios
            SET
                estadoUsuario = 'activo',
                tokenValidacion = NULL
            WHERE codUsuario = :codUsuario
        ";

        $consulta = $conexion->prepare($sql);

        $consulta->execute([
            'codUsuario' => $usuario['codUsuario']
        ]);

        $mensaje = 'Tu cuenta fue validada correctamente.';
    }
}
?>

<?php

$titulo = 'Validar cuenta';

require_once __DIR__ . '/../Includes/header.php';
require_once __DIR__ . '/../Includes/navbar.php';

?>

<main class="section section-gray">

    <div class="container">

        <div class="row justify-content-center">

            <div class="col-md-8 col-lg-5">

                <div class="volara-card text-center">

                    <?php if ($mensaje !== ''): ?>

                        <div class="mb-4">

                            <i
                                class="bi bi-check-circle-fill text-success"
                                style="font-size: 3rem;"
                                aria-hidden="true"
                            ></i>

                        </div>

                        <span class="eyebrow">
                            Cuenta activada
                        </span>

                        <h1 class="section-title mt-2">
                            ¡Cuenta validada!
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

                    <?php endif; ?>


                    <?php if ($error !== ''): ?>

                        <div class="mb-4">

                            <i
                                class="bi bi-exclamation-circle-fill text-danger"
                                style="font-size: 3rem;"
                                aria-hidden="true"
                            ></i>

                        </div>

                        <span class="eyebrow">
                            Validación de cuenta
                        </span>

                        <h1 class="section-title mt-2">
                            No pudimos validar tu cuenta
                        </h1>

                        <div class="volara-alert alert-danger text-start">

                            <i
                                class="bi bi-exclamation-triangle-fill"
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

                        <a
                            href="<?= BASE_URL ?>/Auth/login.php"
                            class="btn-volara-outline w-100"
                        >
                            Volver al inicio de sesión
                        </a>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

</main>

<?php
require_once __DIR__ . '/../Includes/footer.php';
?>