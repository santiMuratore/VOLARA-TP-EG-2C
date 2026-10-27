<?php

require_once __DIR__ . '/../Includes/functions.php';
require_once __DIR__ . '/../Includes/functionsF2.php';

$error = '';
$mail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $mail = trim($_POST['mail'] ?? '');
    $clave = $_POST['clave'] ?? '';

    $conexion = conectarDB();

    $sql = "
        SELECT
            codUsuario,
            mail,
            clave,
            tipoUsuario,
            codAerolinea,
            estadoUsuario
        FROM usuarios
        WHERE mail = :mail
    ";

    $consulta = $conexion->prepare($sql);

    $consulta->execute([
        'mail' => $mail
    ]);

    $usuario = $consulta->fetch(PDO::FETCH_ASSOC);


    if (!$usuario || !password_verify($clave, $usuario['clave'])) {

        $error = 'Mail o contraseña incorrectos.';

    } elseif ($usuario['estadoUsuario'] === 'pendiente de validación') {

        $error = 'Debés validar tu cuenta desde el enlace que enviamos a tu correo.';

    } elseif ($usuario['estadoUsuario'] === 'pendiente de autorización') {

        $error = 'Tu cuenta está pendiente de autorización por un administrador.';

    } elseif ($usuario['estadoUsuario'] === 'rechazado') {

        $error = 'Tu cuenta fue rechazada y no puede iniciar sesión.';

    } elseif ($usuario['estadoUsuario'] === 'activo') {

        session_regenerate_id(true);

        $_SESSION['codUsuario'] = $usuario['codUsuario'];
        $_SESSION['tipoUsuario'] = $usuario['tipoUsuario'];
        $_SESSION['codAerolinea'] = $usuario['codAerolinea'];

        header(
            'Location: '
            . BASE_URL
            . '/Pages/Publico/index.php'
        );

        exit;
    }
}
?>

<?php

$titulo = 'Iniciar sesión';

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
                            Bienvenido
                        </span>

                        <h1 class="section-title mt-2 mb-2">
                            Iniciar sesión
                        </h1>

                        <p class="text-secondary mb-0">
                            Ingresá a tu cuenta de Volara.
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


                        
                        <div class="form-group">

                            <label
                                for="clave"
                                class="volara-label"
                            >
                                Contraseña
                            </label>

                            <input
                                type="password"
                                id="clave"
                                name="clave"
                                class="volara-input"
                                placeholder="Ingresá tu contraseña"
                                required
                            >

                        </div>


                        
                        <div class="text-end mb-4">

                            <a
                                href="<?= BASE_URL ?>/Auth/recuperarClave.php"
                                class="fw-semibold"
                            >
                                ¿Olvidaste tu contraseña?
                            </a>

                        </div>


                        
                        <button
                            type="submit"
                            class="btn-volara btn-volara-lg w-100"
                        >

                            <i
                                class="bi bi-box-arrow-in-right"
                                aria-hidden="true"
                            ></i>

                            Iniciar sesión

                        </button>


                        
                        <p class="text-center text-secondary mt-4 mb-0">

                            ¿Todavía no tenés una cuenta?

                            <a
                                href="<?= BASE_URL ?>/Auth/registro.php"
                                class="fw-semibold"
                            >
                                Registrate
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