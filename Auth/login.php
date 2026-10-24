
<?php
require_once __DIR__ . '/../Includes/functions.php';
require_once __DIR__ . '/../Includes/functionsF2.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$titulo = 'Iniciar sesión';
$pageDescription = 'Ingresá a tu cuenta de VOLARA.';

$baseUrl = rtrim(BASE_URL, '/');
$errores = [];
$mail = '';

if (empty($_SESSION['csrf_login'])) {
    $_SESSION['csrf_login'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_login'];

$maxIntentos = 5;
$tiempoBloqueo = 30;

$intentos = (int) ($_SESSION['intentos_login'] ?? 0);
$ultimoIntento = (int) ($_SESSION['ultimo_intento_login'] ?? 0);

$tiempoRestante = 0;

if ($intentos >= $maxIntentos) {
    $tiempoRestante = $tiempoBloqueo - (time() - $ultimoIntento);

    if ($tiempoRestante <= 0) {
        unset(
            $_SESSION['intentos_login'],
            $_SESSION['ultimo_intento_login']
        );

        $tiempoRestante = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mail = trim($_POST['mail'] ?? '');
    $clave = $_POST['clave'] ?? '';

    if ($tiempoRestante > 0) {
        $errores[] = "Demasiados intentos. Esperá {$tiempoRestante} segundos antes de volver a intentar.";
    } elseif (
        !isset($_POST['csrf_token']) ||
        !hash_equals($csrfToken, (string) $_POST['csrf_token'])
    ) {
        $errores[] = 'El formulario venció. Recargá la página e intentá nuevamente.';
    } elseif ($mail === '' || $clave === '') {
        $errores[] = 'Completá el email y la contraseña.';
    } elseif (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'Ingresá un email válido.';
    } else {
        try {
            $conexion = conectarDB();

            $sql = "
                SELECT
                    codUsuario,
                    nombre,
                    apellido,
                    mail,
                    clave,
                    tipoUsuario,
                    codAerolinea,
                    estadoUsuario
                FROM usuarios
                WHERE mail = :mail
                LIMIT 1
            ";

            $consulta = $conexion->prepare($sql);
            $consulta->execute(['mail' => $mail]);

            $usuario = $consulta->fetch(PDO::FETCH_ASSOC);

            if (
                !$usuario ||
                !password_verify($clave, $usuario['clave'])
            ) {
                $_SESSION['intentos_login'] =
                    (int) ($_SESSION['intentos_login'] ?? 0) + 1;

                $_SESSION['ultimo_intento_login'] = time();

                $errores[] = 'El email o la contraseña son incorrectos.';
            } else {
                $estado = trim((string) $usuario['estadoUsuario']);

                if ($estado === 'pendiente de validación') {
                    $errores[] = 'Debés validar tu cuenta desde el enlace enviado a tu correo.';
                } elseif ($estado === 'pendiente de autorización') {
                    $errores[] = 'Tu cuenta está pendiente de autorización por un administrador.';
                } elseif ($estado === 'rechazado') {
                    $errores[] = 'Tu solicitud fue rechazada. Contactá al administrador si necesitás más información.';
                } elseif ($estado !== 'activo') {
                    $errores[] = 'Tu cuenta no está habilitada para ingresar. Contactá al administrador.';
                } else {
                    session_regenerate_id(true);

                    $_SESSION['codUsuario'] = $usuario['codUsuario'];
                    $_SESSION['tipoUsuario'] = $usuario['tipoUsuario'];
                    $_SESSION['codAerolinea'] = $usuario['codAerolinea'];

                    unset(
                        $_SESSION['intentos_login'],
                        $_SESSION['ultimo_intento_login']
                    );

                    $_SESSION['csrf_login'] = bin2hex(random_bytes(32));

                    header(
                        'Location: ' . $baseUrl . '/Pages/Publico/index.php'
                    );
                    exit;
                }
            }
        } catch (PDOException $e) {
            error_log('Error de login VOLARA: ' . $e->getMessage());
            $errores[] = 'No se pudo procesar el ingreso. Verificá la conexión con la base de datos.';
        }
    }

    // Actualizar el contador si se llegó al límite durante este intento.
    $intentos = (int) ($_SESSION['intentos_login'] ?? 0);

    if ($intentos >= $maxIntentos) {
        $tiempoRestante = max(
            0,
            $tiempoBloqueo - (time() - (int) ($_SESSION['ultimo_intento_login'] ?? time()))
        );
    }
}

require __DIR__ . '/../Includes/header.php';
require __DIR__ . '/../Includes/navbar.php';
?>

<main
    id="contenido-principal"
    class="volara-public-page volara-auth-page"
    tabindex="-1"
>
    <section class="page-header">
        <div class="container">
            <h1>Iniciar sesión</h1>
            <p class="text-muted mb-3">
                Ingresá a tu cuenta y continuá tu experiencia con VOLARA.
            </p>

            <nav aria-label="Ruta de navegación">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="<?= $baseUrl ?>/Pages/Publico/index.php">
                            Inicio
                        </a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        Iniciar sesión
                    </li>
                </ol>
            </nav>
        </div>
    </section>

    <section class="volara-public-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12 col-sm-10 col-md-7 col-lg-5 col-xl-4">

                    <div class="volara-card volara-auth-card">

                        <div class="volara-auth-icon">
                            <i class="bi bi-person-check"></i>
                        </div>

                        <div class="text-center mb-4">
                            <h2 class="h4 mb-2">¡Qué bueno verte!</h2>
                            <p class="text-muted mb-0">
                                Completá tus datos para ingresar.
                            </p>
                        </div>

                        <?php if (!empty($errores)): ?>
                            <div
                                class="alert alert-danger"
                                role="alert"
                                aria-live="polite"
                            >
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($errores as $error): ?>
                                        <li>
                                            <?= htmlspecialchars(
                                                $error,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" autocomplete="on">
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars(
                                    $csrfToken,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >

                            <div class="form-group mb-3">
                                <label class="volara-label" for="mail">
                                    Email
                                </label>

                                <input
                                    type="email"
                                    class="volara-input"
                                    id="mail"
                                    name="mail"
                                    value="<?= htmlspecialchars(
                                        $mail,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    placeholder="tu@email.com"
                                    autocomplete="email"
                                    maxlength="254"
                                    required
                                >
                            </div>

                            <div class="form-group mb-3">
                                <label class="volara-label" for="clave">
                                    Contraseña
                                </label>

                                <div class="input-group volara-auth-password">
                                    <input
                                        type="password"
                                        class="form-control volara-input"
                                        id="clave"
                                        name="clave"
                                        placeholder="Ingresá tu contraseña"
                                        autocomplete="current-password"
                                        required
                                    >

                                    <button
                                        type="button"
                                        class="btn btn-outline-secondary"
                                        data-toggle-password="clave"
                                        aria-label="Mostrar contraseña"
                                        aria-pressed="false"
                                    >
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>

                            <button
                                type="submit"
                                class="btn btn-volara btn-volara-lg w-100 mt-3"
                                <?= $tiempoRestante > 0 ? 'disabled' : '' ?>
                            >
                                <i class="bi bi-box-arrow-in-right me-2"></i>
                                Iniciar sesión
                            </button>
                        </form>
                        <br>
                        <div class="volara-auth-divider">
                            <span>¿Sos nuevo en VOLARA?</span>
                        </div>

                        <a
                            href="<?= $baseUrl ?>/Auth/registro.php"
                            class="btn btn-volara-outline w-100"
                        >
                            Crear una cuenta
                        </a>

                        <p class="text-muted small text-center mt-4 mb-0">
                            Tu próximo destino comienza acá.
                        </p>

                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
    button.addEventListener('click', function () {
        const inputId = button.dataset.togglePassword;
        const input = document.getElementById(inputId);

        if (!input) {
            return;
        }

        const mostrar = input.type === 'password';
        input.type = mostrar ? 'text' : 'password';

        button.setAttribute('aria-pressed', String(mostrar));
        button.setAttribute(
            'aria-label',
            mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña'
        );

        const icono = button.querySelector('i');

        if (icono) {
            icono.classList.toggle('bi-eye', !mostrar);
            icono.classList.toggle('bi-eye-slash', mostrar);
        }
    });
});
</script>

<?php require __DIR__ . '/../Includes/footer.php'; ?>
