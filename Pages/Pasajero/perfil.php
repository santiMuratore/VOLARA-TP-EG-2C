<?php

/*
|--------------------------------------------------------------------------
| Sesión
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Buscar los archivos Includes
|--------------------------------------------------------------------------
*/

$directoriosPosibles = [
    dirname(__DIR__) . '/Includes',
    dirname(__DIR__, 2) . '/Includes',
];

$directorioIncludes = null;

foreach ($directoriosPosibles as $directorio) {
    if (
        is_file($directorio . '/functions.php') &&
        is_file($directorio . '/functionsF2.php')
    ) {
        $directorioIncludes = $directorio;
        break;
    }
}

if ($directorioIncludes === null) {
    error_log(
        'VOLARA: no se encontraron functions.php y functionsF2.php.'
    );

    http_response_code(500);
    exit('Error de configuración. Verificá la ubicación de los archivos Includes.');
}

/*
|--------------------------------------------------------------------------
| Cargar funciones compartidas
|--------------------------------------------------------------------------
*/

require_once $directorioIncludes . '/functions.php';
require_once $directorioIncludes . '/functionsF2.php';

if (!defined('BASE_URL')) {
    http_response_code(500);
    exit('Error de configuración: BASE_URL no está definida.');
}

$baseUrl = rtrim(BASE_URL, '/');

/*
|--------------------------------------------------------------------------
| Verificar sesión
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['codUsuario'])) {
    header('Location: ' . $baseUrl . '/Auth/login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Verificar rol del usuario
|--------------------------------------------------------------------------
*/

$rolUsuario = trim((string) (
    $_SESSION['tipoUsuario']
    ?? $_SESSION['rol']
    ?? ''
));

$rolUsuario = function_exists('mb_strtolower')
    ? mb_strtolower($rolUsuario, 'UTF-8')
    : strtolower($rolUsuario);

$rolUsuario = strtr($rolUsuario, [
    'á' => 'a',
    'é' => 'e',
    'í' => 'i',
    'ó' => 'o',
    'ú' => 'u',
    'ü' => 'u',
]);

$rolesPermitidosPerfil = [
    'admin',
    'administrador',
    'ceo',
    'ceo aerolinea',
    'ceo de aerolinea',
    'usuario',
    'pasajero',
    'cliente',
];

if (!in_array($rolUsuario, $rolesPermitidosPerfil, true)) {
    header('Location: ' . $baseUrl . '/Pages/Publico/index.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Datos de la página
|--------------------------------------------------------------------------
*/

$titulo = 'Mi perfil';
$pageDescription = 'Consultá y actualizá los datos de tu cuenta VOLARA.';

$errores = [];
$mensajeExito = '';
$perfil = null;

/*
|--------------------------------------------------------------------------
| Token CSRF
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_perfil'])) {
    $_SESSION['csrf_perfil'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_perfil'];

/*
|--------------------------------------------------------------------------
| Obtener perfil
|--------------------------------------------------------------------------
*/

try {
    $conexion = conectarDB();

    $consulta = $conexion->prepare("
        SELECT
            codUsuario,
            nombre,
            apellido,
            mail,
            telefono,
            clave,
            tipoUsuario
        FROM usuarios
        WHERE codUsuario = :codUsuario
        LIMIT 1
    ");

    $consulta->execute([
        'codUsuario' => $_SESSION['codUsuario'],
    ]);

    $perfil = $consulta->fetch(PDO::FETCH_ASSOC);

    if (!$perfil) {
        $errores[] = 'No se encontró tu cuenta. Volvé a iniciar sesión.';
    }
} catch (PDOException $e) {
    error_log('Error cargando perfil VOLARA: ' . $e->getMessage());

    $errores[] = 'No se pudo cargar tu perfil. Intentá nuevamente.';
}

/*
|--------------------------------------------------------------------------
| Valores iniciales del formulario
|--------------------------------------------------------------------------
*/

$nombre = (string) ($perfil['nombre'] ?? '');
$apellido = (string) ($perfil['apellido'] ?? '');
$telefono = (string) ($perfil['telefono'] ?? '');

$claveActual = '';
$claveNueva = '';
$confirmarClave = '';

/*
|--------------------------------------------------------------------------
| Procesar actualización
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $perfil) {

    $nombre = trim((string) ($_POST['nombre'] ?? ''));
    $apellido = trim((string) ($_POST['apellido'] ?? ''));
    $telefono = trim((string) ($_POST['telefono'] ?? ''));

    $claveActual = (string) ($_POST['claveActual'] ?? '');
    $claveNueva = (string) ($_POST['claveNueva'] ?? '');
    $confirmarClave = (string) ($_POST['confirmarClave'] ?? '');

    /*
     * Validar el formulario.
     */
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals(
            $csrfToken,
            (string) $_POST['csrf_token']
        )
    ) {
        $errores[] = 'El formulario venció. Recargá la página e intentá nuevamente.';
    }

    $longitudNombre = function_exists('mb_strlen')
        ? mb_strlen($nombre, 'UTF-8')
        : strlen($nombre);

    $longitudApellido = function_exists('mb_strlen')
        ? mb_strlen($apellido, 'UTF-8')
        : strlen($apellido);

    $longitudTelefono = function_exists('mb_strlen')
        ? mb_strlen($telefono, 'UTF-8')
        : strlen($telefono);

    if (
        $longitudNombre < 2 ||
        $longitudNombre > 80
    ) {
        $errores[] = 'El nombre debe tener entre 2 y 80 caracteres.';
    }

    if (
        $longitudApellido < 2 ||
        $longitudApellido > 80
    ) {
        $errores[] = 'El apellido debe tener entre 2 y 80 caracteres.';
    }

    if ($longitudTelefono > 30) {
        $errores[] = 'El teléfono no puede superar los 30 caracteres.';
    }

    /*
     * Comprobar si se quiere cambiar la contraseña.
     */
    $cambiarClave = (
        $claveActual !== '' ||
        $claveNueva !== '' ||
        $confirmarClave !== ''
    );

    if ($cambiarClave) {

        if (
            $claveActual === '' ||
            !password_verify(
                $claveActual,
                (string) $perfil['clave']
            )
        ) {
            $errores[] = 'La contraseña actual es incorrecta.';
        }

        if (strlen($claveNueva) < 8) {
            $errores[] = 'La nueva contraseña debe tener al menos 8 caracteres.';
        }

        if ($claveNueva !== $confirmarClave) {
            $errores[] = 'Las nuevas contraseñas no coinciden.';
        }
    }

    /*
     * Guardar los cambios si no existen errores.
     */
    if (empty($errores)) {

        try {

            if ($cambiarClave) {

                $sql = "
                    UPDATE usuarios
                    SET
                        nombre = :nombre,
                        apellido = :apellido,
                        telefono = :telefono,
                        clave = :clave
                    WHERE codUsuario = :codUsuario
                ";

                $parametros = [
                    'nombre' => $nombre,
                    'apellido' => $apellido,
                    'telefono' => $telefono !== ''
                        ? $telefono
                        : null,
                    'clave' => password_hash(
                        $claveNueva,
                        PASSWORD_DEFAULT
                    ),
                    'codUsuario' => $_SESSION['codUsuario'],
                ];
            } else {

                $sql = "
                    UPDATE usuarios
                    SET
                        nombre = :nombre,
                        apellido = :apellido,
                        telefono = :telefono
                    WHERE codUsuario = :codUsuario
                ";

                $parametros = [
                    'nombre' => $nombre,
                    'apellido' => $apellido,
                    'telefono' => $telefono !== ''
                        ? $telefono
                        : null,
                    'codUsuario' => $_SESSION['codUsuario'],
                ];
            }

            $consulta = $conexion->prepare($sql);
            $consulta->execute($parametros);

            /*
             * Renovar el token después de guardar.
             */
            $_SESSION['csrf_perfil'] = bin2hex(random_bytes(32));
            $csrfToken = $_SESSION['csrf_perfil'];

            $mensajeExito = 'Tu perfil se actualizó correctamente.';

            /*
             * Limpiar contraseñas del formulario.
             */
            $claveActual = '';
            $claveNueva = '';
            $confirmarClave = '';

            /*
             * Recargar los datos actualizados.
             */
            $consulta = $conexion->prepare("
                SELECT
                    codUsuario,
                    nombre,
                    apellido,
                    mail,
                    telefono,
                    clave,
                    tipoUsuario
                FROM usuarios
                WHERE codUsuario = :codUsuario
                LIMIT 1
            ");

            $consulta->execute([
                'codUsuario' => $_SESSION['codUsuario'],
            ]);

            $perfil = $consulta->fetch(PDO::FETCH_ASSOC);

            $nombre = (string) ($perfil['nombre'] ?? '');
            $apellido = (string) ($perfil['apellido'] ?? '');
            $telefono = (string) ($perfil['telefono'] ?? '');

            // Mantener actualizados los datos disponibles para la sesión.
            $_SESSION['nombre'] = $nombre;
            $_SESSION['apellido'] = $apellido;
        } catch (PDOException $e) {
            error_log('Error actualizando perfil VOLARA: ' . $e->getMessage());

            $errores[] = 'No se pudieron guardar los cambios. Intentá nuevamente.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| Buscar plantillas compartidas
|--------------------------------------------------------------------------
*/

$archivoHeader = $directorioIncludes . '/header.php';
$archivoNavbar = $directorioIncludes . '/navbar.php';
$archivoFooter = $directorioIncludes . '/footer.php';

if (
    !is_file($archivoHeader) ||
    !is_file($archivoNavbar) ||
    !is_file($archivoFooter)
) {
    error_log('VOLARA: falta alguna plantilla compartida del sitio.');

    http_response_code(500);
    exit('No se encontraron las plantillas compartidas de VOLARA.');
}

/*
|--------------------------------------------------------------------------
| Encabezado y navegación
|--------------------------------------------------------------------------
*/

require $archivoHeader;
require $archivoNavbar;

?>

<main
    id="contenido-principal"
    class="volara-public-page volara-pasajero-page"
    tabindex="-1">
    <!-- Encabezado de página -->
    <section class="page-header">
        <div class="container">

            <h1>Mi perfil</h1>

            <p class="text-muted mb-3">
                Administrá tus datos personales y la seguridad de tu cuenta.
            </p>

            <nav aria-label="Ruta de navegación">
                <ol class="breadcrumb mb-0">

                    <li class="breadcrumb-item">
                        <a href="<?= htmlspecialchars(
                                        $baseUrl . '/Pages/Publico/index.php',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>">
                            Inicio
                        </a>
                    </li>

                    <li class="breadcrumb-item active" aria-current="page">
                        Mi perfil
                    </li>

                </ol>
            </nav>

        </div>
    </section>

    <!-- Formulario de perfil -->
    <section class="volara-public-section">
        <div class="container">

            <div class="row justify-content-center">
                <div class="col-12 col-lg-8 col-xl-7">

                    <div class="volara-card profile-page-card">

                        <?php if ($mensajeExito !== ''): ?>

                            <div
                                class="alert alert-success"
                                role="status"
                                aria-live="polite">
                                <i
                                    class="bi bi-check-circle me-2"
                                    aria-hidden="true"></i>

                                <?= htmlspecialchars(
                                    $mensajeExito,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </div>

                        <?php endif; ?>

                        <?php if (!empty($errores)): ?>

                            <div
                                class="alert alert-danger"
                                role="alert"
                                aria-live="polite">
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

                        <?php if ($perfil): ?>

                            <!-- Resumen del usuario -->
                            <div class="d-flex align-items-center gap-3 mb-4">

                                <?php
                                $iniciales = strtoupper(
                                    substr($nombre, 0, 1)
                                        . substr($apellido, 0, 1)
                                );
                                ?>

                                <span
                                    class="user-avatar profile-avatar"
                                    aria-hidden="true">
                                    <?= htmlspecialchars(
                                        $iniciales ?: 'U',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>

                                <div class="min-w-0">

                                    <h2 class="h4 mb-1">
                                        <?= htmlspecialchars(
                                            trim($nombre . ' ' . $apellido),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </h2>

                                    <p class="text-muted mb-0 text-break">
                                        <?= htmlspecialchars(
                                            (string) $perfil['mail'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </p>

                                </div>

                            </div>

                            <hr class="my-4">

                            <form method="POST" action="">

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars(
                                                $csrfToken,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>">

                                <!-- Datos personales -->
                                <h3 class="h5 mb-3">Datos personales</h3>

                                <div class="row g-3">

                                    <div class="col-12 col-md-6">

                                        <label
                                            class="volara-label"
                                            for="nombre">
                                            Nombre *
                                        </label>

                                        <input
                                            type="text"
                                            class="volara-input"
                                            id="nombre"
                                            name="nombre"
                                            value="<?= htmlspecialchars(
                                                        $nombre,
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>"
                                            minlength="2"
                                            maxlength="80"
                                            autocomplete="given-name"
                                            required>

                                    </div>

                                    <div class="col-12 col-md-6">

                                        <label
                                            class="volara-label"
                                            for="apellido">
                                            Apellido *
                                        </label>

                                        <input
                                            type="text"
                                            class="volara-input"
                                            id="apellido"
                                            name="apellido"
                                            value="<?= htmlspecialchars(
                                                        $apellido,
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>"
                                            minlength="2"
                                            maxlength="80"
                                            autocomplete="family-name"
                                            required>

                                    </div>

                                    <div class="col-12">

                                        <label
                                            class="volara-label"
                                            for="mail">
                                            Email
                                        </label>

                                        <input
                                            type="email"
                                            class="volara-input"
                                            id="mail"
                                            value="<?= htmlspecialchars(
                                                        (string) $perfil['mail'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>"
                                            autocomplete="email"
                                            readonly>

                                        <small class="text-muted d-block mt-1">
                                            El correo de acceso no se modifica desde esta pantalla.
                                        </small>

                                    </div>

                                    <div class="col-12">

                                        <label
                                            class="volara-label"
                                            for="telefono">
                                            Teléfono
                                        </label>

                                        <input
                                            type="tel"
                                            class="volara-input"
                                            id="telefono"
                                            name="telefono"
                                            value="<?= htmlspecialchars(
                                                        $telefono,
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>"
                                            maxlength="30"
                                            autocomplete="tel"
                                            placeholder="Tu número de teléfono">

                                    </div>

                                </div>

                                <hr class="my-4">

                                <!-- Contraseña -->
                                <h3 class="h5 mb-2">Seguridad</h3>

                                <p class="text-muted small mb-3">
                                    Dejá estos campos vacíos si no querés cambiar tu contraseña.
                                </p>

                                <div class="row g-3">

                                    <div class="col-12">

                                        <label
                                            class="volara-label"
                                            for="claveActual">
                                            Contraseña actual
                                        </label>

                                        <input
                                            type="password"
                                            class="volara-input"
                                            id="claveActual"
                                            name="claveActual"
                                            autocomplete="current-password">

                                    </div>

                                    <div class="col-12 col-md-6">

                                        <label
                                            class="volara-label"
                                            for="claveNueva">
                                            Nueva contraseña
                                        </label>

                                        <input
                                            type="password"
                                            class="volara-input"
                                            id="claveNueva"
                                            name="claveNueva"
                                            minlength="8"
                                            autocomplete="new-password">

                                        <small class="text-muted d-block mt-1">
                                            Debe tener al menos 8 caracteres.
                                        </small>

                                    </div>

                                    <div class="col-12 col-md-6">

                                        <label
                                            class="volara-label"
                                            for="confirmarClave">
                                            Confirmar contraseña
                                        </label>

                                        <input
                                            type="password"
                                            class="volara-input"
                                            id="confirmarClave"
                                            name="confirmarClave"
                                            minlength="8"
                                            autocomplete="new-password">

                                    </div>

                                </div>


                                <div class="d-flex flex-wrap gap-2 mt-4">

                                    <button
                                        type="submit"
                                        class="btn btn-volara">
                                        <i class="bi bi-check-lg me-1" aria-hidden="true"></i>
                                        Guardar cambios
                                    </button>

                                    <?php if (in_array($rolUsuario, ['usuario', 'pasajero', 'cliente'], true)): ?>
                                        <a
                                            href="<?= htmlspecialchars(
                                                        $baseUrl . '/Pages/Pasajero/mis-reservas.php',
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>"
                                            class="btn btn-volara-outline">
                                            Ver mis reservas
                                        </a>
                                    <?php endif; ?>

                                </div>


                            </form>

                        <?php endif; ?>

                    </div>

                </div>
            </div>

        </div>
    </section>
</main>

<?php require $archivoFooter; ?>