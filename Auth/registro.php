
<?php
require_once __DIR__ . '/../Includes/functions.php';
require_once __DIR__ . '/../Includes/functionsF2.php';
// require_once __DIR__ . '/../Includes/mailer.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$titulo = 'Crear cuenta';
$pageDescription = 'Registrate en VOLARA para acceder a sus servicios.';

$baseUrl = rtrim(BASE_URL, '/');

$errores = [];
$aerolineas = [];
$registroCompletado = false;
$mensajeExito = '';
$tipoMensaje = 'success';

$nombre = '';
$apellido = '';
$mail = '';
$telefono = '';
$tipoUsuario = 'usuario';
$codAerolinea = '';
$clave = '';
$confirmarClave = '';

if (empty($_SESSION['csrf_registro'])) {
    $_SESSION['csrf_registro'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_registro'];

// Cargar aerolíneas para el selector.
$conexion = null;

try {
    $conexion = conectarDB();

    $sqlAerolineas = "
        SELECT codAerolinea, nombre
        FROM aerolineas
        ORDER BY nombre
    ";

    $aerolineas = $conexion
        ->query($sqlAerolineas)
        ->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error cargando aerolíneas VOLARA: ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errores = [];

    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $mail = trim($_POST['mail'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');

    $clave = $_POST['clave'] ?? '';
    $confirmarClave = $_POST['confirmarClave'] ?? '';

    $tipoUsuario = $_POST['tipoUsuario'] ?? 'usuario';
    $codAerolinea = trim((string) ($_POST['codAerolinea'] ?? ''));

    // Protección CSRF.
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($csrfToken, (string) $_POST['csrf_token'])
    ) {
        $errores[] = 'El formulario venció. Recargá la página e intentá nuevamente.';
    }

    // Validación de los datos personales.
    if ($nombre === '') {
        $errores[] = 'El nombre es obligatorio.';
    } elseif (mb_strlen($nombre) < 2) {
        $errores[] = 'El nombre debe contener al menos 2 caracteres.';
    }

    if ($apellido === '') {
        $errores[] = 'El apellido es obligatorio.';
    } elseif (mb_strlen($apellido) < 2) {
        $errores[] = 'El apellido debe contener al menos 2 caracteres.';
    }

    if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'Ingresá un email válido.';
    }

    if (strlen($mail) > 254) {
        $errores[] = 'El email es demasiado largo.';
    }

    if (strlen($telefono) > 30) {
        $errores[] = 'El teléfono no puede superar los 30 caracteres.';
    }

    // Validación de contraseña.
    if (strlen($clave) < 8) {
        $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
    }

    if ($clave !== $confirmarClave) {
        $errores[] = 'Las contraseñas no coinciden.';
    }

    // Validación del tipo de cuenta.
    if (!in_array($tipoUsuario, ['usuario', 'ceo aerolínea'], true)) {
        $errores[] = 'Seleccioná un tipo de cuenta válido.';
    }

    // La aerolínea solo es obligatoria para el CEO.
    if ($tipoUsuario !== 'ceo aerolínea') {
        $codAerolinea = '';
    } elseif ($codAerolinea === '') {
        $errores[] = 'Debés seleccionar la aerolínea que representás.';
    }

    // Si la conexión inicial falló, intentar conectarse nuevamente.
    if (!$conexion) {
        try {
            $conexion = conectarDB();
        } catch (PDOException $e) {
            error_log('Error de conexión al registrar VOLARA: ' . $e->getMessage());
            $errores[] = 'No se pudo conectar con la base de datos. Intentá nuevamente.';
        }
    }

    if (empty($errores) && $conexion) {
        try {
            // Comprobar si el email ya existe.
            $consulta = $conexion->prepare("
                SELECT codUsuario
                FROM usuarios
                WHERE mail = :mail
                LIMIT 1
            ");

            $consulta->execute(['mail' => $mail]);

            if ($consulta->fetch(PDO::FETCH_ASSOC)) {
                $errores[] = 'Ya existe una cuenta registrada con ese email.';
            }

            // Comprobar la aerolínea seleccionada.
            if (
                empty($errores) &&
                $tipoUsuario === 'ceo aerolínea'
            ) {
                $consulta = $conexion->prepare("
                    SELECT codAerolinea
                    FROM aerolineas
                    WHERE codAerolinea = :codAerolinea
                    LIMIT 1
                ");

                $consulta->execute([
                    'codAerolinea' => $codAerolinea
                ]);

                if (!$consulta->fetch(PDO::FETCH_ASSOC)) {
                    $errores[] = 'La aerolínea seleccionada no existe.';
                }
            }

            if (empty($errores)) {
                $claveHash = password_hash($clave, PASSWORD_DEFAULT);

                if ($tipoUsuario === 'usuario') {
                    $estadoUsuario = 'pendiente de validación';
                    $tokenValidacion = bin2hex(random_bytes(32));
                } else {
                    $estadoUsuario = 'pendiente de autorización';
                    $tokenValidacion = null;
                }

                $sql = "
                    INSERT INTO usuarios (
                        nombre,
                        apellido,
                        mail,
                        clave,
                        telefono,
                        tipoUsuario,
                        codAerolinea,
                        estadoUsuario,
                        tokenValidacion
                    ) VALUES (
                        :nombre,
                        :apellido,
                        :mail,
                        :clave,
                        :telefono,
                        :tipoUsuario,
                        :codAerolinea,
                        :estadoUsuario,
                        :tokenValidacion
                    )
                ";

                $consulta = $conexion->prepare($sql);

                $consulta->execute([
                    'nombre' => $nombre,
                    'apellido' => $apellido,
                    'mail' => $mail,
                    'clave' => $claveHash,
                    'telefono' => $telefono !== '' ? $telefono : null,
                    'tipoUsuario' => $tipoUsuario,
                    'codAerolinea' => $tipoUsuario === 'ceo aerolínea'
                        ? $codAerolinea
                        : null,
                    'estadoUsuario' => $estadoUsuario,
                    'tokenValidacion' => $tokenValidacion
                ]);

                $registroCompletado = true;

                if ($tipoUsuario === 'usuario') {
                    $enlaceValidacion = $baseUrl
                        . '/Auth/validarCuenta.php?token='
                        . rawurlencode($tokenValidacion);

                    $nombreSeguro = htmlspecialchars(
                        $nombre,
                        ENT_QUOTES,
                        'UTF-8'
                    );

                    $enlaceSeguro = htmlspecialchars(
                        $enlaceValidacion,
                        ENT_QUOTES,
                        'UTF-8'
                    );

                    $cuerpo = "
                        <h2>Bienvenido a VOLARA</h2>
                        <p>Hola {$nombreSeguro}, gracias por registrarte.</p>
                        <p>Para activar tu cuenta, ingresá en el siguiente enlace:</p>
                        <p>
                            <a href=\"{$enlaceSeguro}\">
                                Validar mi cuenta
                            </a>
                        </p>
                    ";

                    $mailEnviado = false;

                    try {
                        $mailEnviado = enviarMail(
                            $mail,
                            'Validá tu cuenta de VOLARA',
                            $cuerpo
                        );
                    } catch (Throwable $e) {
                        error_log('Error enviando activación VOLARA: ' . $e->getMessage());
                    }

                    if ($mailEnviado) {
                        $mensajeExito =
                            'Tu cuenta se creó correctamente. Revisá tu correo y seguí el enlace para activarla.';
                    } else {
                        $tipoMensaje = 'warning';
                        $mensajeExito =
                            'Tu cuenta se creó, pero no pudimos enviar el correo de validación. Revisá la configuración del servidor de correo antes de continuar.';
                    }
                } else {
                    $mensajeExito =
                        'Tu solicitud de CEO se registró correctamente. Un administrador debe autorizar tu cuenta antes de que puedas iniciar sesión.';
                }

                // No conservar datos sensibles ni permitir reutilizar el token del formulario.
                $clave = '';
                $confirmarClave = '';

                $_SESSION['csrf_registro'] = bin2hex(random_bytes(32));
                $csrfToken = $_SESSION['csrf_registro'];
            }
        } catch (PDOException $e) {
            error_log('Error de registro VOLARA: ' . $e->getMessage());

            $errores[] = 'No se pudo completar el registro. Verificá los datos e intentá nuevamente.';
        }
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
            <h1>Crear cuenta</h1>
            <p class="text-muted mb-3">
                Unite a VOLARA y prepará tu próximo viaje.
            </p>

            <nav aria-label="Ruta de navegación">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="<?= $baseUrl ?>/Pages/Publico/index.php">
                            Inicio
                        </a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        Registrarse
                    </li>
                </ol>
            </nav>
        </div>
    </section>

    <section class="volara-public-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12 col-md-9 col-lg-7 col-xl-6">

                    <div class="volara-card volara-auth-card">

                        <?php if ($registroCompletado): ?>

                            <div class="volara-auth-icon">
                                <i class="bi bi-check2-circle"></i>
                            </div>

                            <div class="text-center">
                                <h2 class="h4 mb-3">
                                    ¡Gracias por registrarte!
                                </h2>

                                <div
                                    class="alert alert-<?= htmlspecialchars($tipoMensaje, ENT_QUOTES, 'UTF-8') ?>"
                                    role="status"
                                    aria-live="polite"
                                >
                                    <?= htmlspecialchars(
                                        $mensajeExito,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </div>

                                <a
                                    href="<?= $baseUrl ?>/Pages/Publico/login.php"
                                    class="btn btn-volara btn-volara-lg w-100 mt-3"
                                >
                                    Ir a iniciar sesión
                                </a>

                                <a
                                    href="<?= $baseUrl ?>/Pages/Publico/index.php"
                                    class="btn btn-volara-outline w-100 mt-2"
                                >
                                    Volver al inicio
                                </a>
                            </div>

                        <?php else: ?>

                            <div class="volara-auth-icon">
                                <i class="bi bi-person-plus"></i>
                            </div>

                            <div class="text-center mb-4">
                                <h2 class="h4 mb-2">
                                    Creá tu cuenta
                                </h2>
                                <p class="text-muted mb-0">
                                    Completá tus datos para comenzar.
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

                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <label class="volara-label" for="nombre">
                                            Nombre *
                                        </label>

                                        <input
                                            type="text"
                                            class="volara-input"
                                            id="nombre"
                                            name="nombre"
                                            value="<?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') ?>"
                                            placeholder="Tu nombre"
                                            autocomplete="given-name"
                                            minlength="2"
                                            maxlength="100"
                                            required
                                        >
                                    </div>

                                    <div class="col-12 col-md-6">
                                        <label class="volara-label" for="apellido">
                                            Apellido *
                                        </label>

                                        <input
                                            type="text"
                                            class="volara-input"
                                            id="apellido"
                                            name="apellido"
                                            value="<?= htmlspecialchars($apellido, ENT_QUOTES, 'UTF-8') ?>"
                                            placeholder="Tu apellido"
                                            autocomplete="family-name"
                                            minlength="2"
                                            maxlength="100"
                                            required
                                        >
                                    </div>

                                    <div class="col-12">
                                        <label class="volara-label" for="mail">
                                            Email *
                                        </label>

                                        <input
                                            type="email"
                                            class="volara-input"
                                            id="mail"
                                            name="mail"
                                            value="<?= htmlspecialchars($mail, ENT_QUOTES, 'UTF-8') ?>"
                                            placeholder="tu@email.com"
                                            autocomplete="email"
                                            maxlength="254"
                                            required
                                        >
                                    </div>

                                    <div class="col-12">
                                        <label class="volara-label" for="telefono">
                                            Teléfono
                                        </label>

                                        <input
                                            type="tel"
                                            class="volara-input"
                                            id="telefono"
                                            name="telefono"
                                            value="<?= htmlspecialchars($telefono, ENT_QUOTES, 'UTF-8') ?>"
                                            placeholder="Tu número de teléfono"
                                            autocomplete="tel"
                                            maxlength="30"
                                        >
                                    </div>

                                    <div class="col-12">
                                        <label class="volara-label" for="tipoUsuario">
                                            Tipo de cuenta *
                                        </label>

                                        <select
                                            class="volara-select"
                                            id="tipoUsuario"
                                            name="tipoUsuario"
                                            required
                                        >
                                            <option
                                                value="usuario"
                                                <?= $tipoUsuario === 'usuario' ? 'selected' : '' ?>
                                            >
                                                Pasajero
                                            </option>

                                            <option
                                                value="ceo aerolínea"
                                                <?= $tipoUsuario === 'ceo aerolínea' ? 'selected' : '' ?>
                                            >
                                                CEO de aerolínea
                                            </option>
                                        </select>

                                        <small class="text-muted d-block mt-2">
                                            Las cuentas de CEO requieren autorización administrativa.
                                        </small>
                                    </div>

                                    <div
                                        class="col-12"
                                        id="grupoAerolinea"
                                        <?= $tipoUsuario === 'ceo aerolínea' ? '' : 'hidden' ?>
                                    >
                                        <label class="volara-label" for="codAerolinea">
                                            Aerolínea *
                                        </label>

                                        <select
                                            class="volara-select"
                                            id="codAerolinea"
                                            name="codAerolinea"
                                            <?= $tipoUsuario === 'ceo aerolínea' ? 'required' : '' ?>
                                        >
                                            <option value="">
                                                Seleccioná una aerolínea
                                            </option>

                                            <?php foreach ($aerolineas as $aerolinea): ?>
                                                <option
                                                    value="<?= htmlspecialchars(
                                                        (string) $aerolinea['codAerolinea'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>"
                                                    <?= (string) $codAerolinea === (string) $aerolinea['codAerolinea'] ? 'selected' : '' ?>
                                                >
                                                    <?= htmlspecialchars(
                                                        $aerolinea['nombre'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-12">
                                        <label class="volara-label" for="clave">
                                            Contraseña *
                                        </label>

                                        <div class="input-group volara-auth-password">
                                            <input
                                                type="password"
                                                class="form-control volara-input"
                                                id="clave"
                                                name="clave"
                                                placeholder="Mínimo 8 caracteres"
                                                minlength="8"
                                                autocomplete="new-password"
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

                                    <div class="col-12">
                                        <label class="volara-label" for="confirmarClave">
                                            Confirmar contraseña *
                                        </label>

                                        <div class="input-group volara-auth-password">
                                            <input
                                                type="password"
                                                class="form-control volara-input"
                                                id="confirmarClave"
                                                name="confirmarClave"
                                                placeholder="Repetí tu contraseña"
                                                minlength="8"
                                                autocomplete="new-password"
                                                required
                                            >

                                            <button
                                                type="button"
                                                class="btn btn-outline-secondary"
                                                data-toggle-password="confirmarClave"
                                                aria-label="Mostrar contraseña"
                                                aria-pressed="false"
                                            >
                                                <i class="bi bi-eye" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <button
                                            type="submit"
                                            class="btn btn-volara btn-volara-lg w-100 mt-2"
                                        >
                                            <i class="bi bi-person-check me-2"></i>
                                            Crear cuenta
                                        </button>
                                    </div>
                                </div>
                            </form>
                            <br

                            <div class="volara-auth-divider">
                                <span>¿Ya tenés una cuenta?</span>
                            </div>

                            <a
                                href="<?= $baseUrl ?>/Auth/login.php"
                                class="btn btn-volara-outline w-100"
                            >
                                Iniciar sesión
                            </a>

                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
(function () {
    const tipoUsuario = document.getElementById('tipoUsuario');
    const grupoAerolinea = document.getElementById('grupoAerolinea');
    const codAerolinea = document.getElementById('codAerolinea');

    function actualizarAerolinea() {
        if (!tipoUsuario || !grupoAerolinea || !codAerolinea) {
            return;
        }

        const esCeo = tipoUsuario.value === 'ceo aerolínea';

        grupoAerolinea.hidden = !esCeo;
        codAerolinea.required = esCeo;

        if (!esCeo) {
            codAerolinea.value = '';
        }
    }

    if (tipoUsuario) {
        tipoUsuario.addEventListener('change', actualizarAerolinea);
        actualizarAerolinea();
    }

    document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
        button.addEventListener('click', function () {
            const input = document.getElementById(button.dataset.togglePassword);

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
})();
</script>

<?php require __DIR__ . '/../Includes/footer.php'; ?>
