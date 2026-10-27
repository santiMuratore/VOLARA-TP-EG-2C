<?php
/** Crear novedad de VOLARA. Ubicación: Pages/Admin/novedades/crear.php */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../../Includes/functions.php';
require_once __DIR__ . '/../../../Includes/functionsF2.php';

$baseUrl = rtrim(BASE_URL, '/');
$urlListado = $baseUrl . '/Pages/Admin/novedades/index.php';
$escapar = static function ($valor): string {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
};

/* Control de acceso */
$datosUsuario = [];
if (function_exists('usuarioActual')) {
    $usuarioConsultado = usuarioActual();
    if (is_array($usuarioConsultado)) {
        $datosUsuario = $usuarioConsultado;
    }
}
$rol = trim((string) (
    $datosUsuario['rol'] ?? $datosUsuario['tipoUsuario'] ?? $_SESSION['tipoUsuario'] ?? ''
));
$rol = function_exists('mb_strtolower') ? mb_strtolower($rol, 'UTF-8') : strtolower($rol);
$rol = strtr($rol, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']);

if (empty($_SESSION['codUsuario'])) {
    header('Location: ' . $baseUrl . '/Auth/login.php');
    exit;
}
if (!in_array($rol, ['admin', 'administrador'], true)) {
    header('Location: ' . $baseUrl . '/Pages/Publico/index.php');
    exit;
}

$titulo = 'Crear novedad';
$pageDescription = 'Publicá una nueva novedad en VOLARA.';
$errores = [];
$mensaje = '';
$tituloNovedad = '';
$texto = '';
$fechaPublicacion = date('Y-m-d');
$fechaExpiracion = '';

$validarFecha = static function (string $fecha): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        return false;
    }
    $objetoFecha = DateTime::createFromFormat('!Y-m-d', $fecha);
    $erroresFecha = DateTime::getLastErrors();
    return $objetoFecha !== false
        && $objetoFecha->format('Y-m-d') === $fecha
        && ($erroresFecha === false || ($erroresFecha['warning_count'] === 0 && $erroresFecha['error_count'] === 0));
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? null;
    if (!is_string($token) || !csrfValido($token)) {
        $errores[] = 'La sesión del formulario venció. Recargá la página e intentá nuevamente.';
    }

    $tituloNovedad = trim((string) ($_POST['titulo'] ?? ''));
    $texto = trim((string) ($_POST['texto'] ?? ''));
    $fechaPublicacion = trim((string) ($_POST['fechaPublicacion'] ?? ''));
    $fechaExpiracion = trim((string) ($_POST['fechaExpiracion'] ?? ''));

    $longitudTitulo = function_exists('mb_strlen') ? mb_strlen($tituloNovedad, 'UTF-8') : strlen($tituloNovedad);
    if ($longitudTitulo < 3 || $longitudTitulo > 200) {
        $errores[] = 'El título debe tener entre 3 y 200 caracteres.';
    }
    if ($texto === '') {
        $errores[] = 'El texto de la novedad es obligatorio.';
    } elseif (strlen($texto) > 60000) {
        $errores[] = 'El texto es demasiado largo. Reducí su extensión e intentá nuevamente.';
    }
    if (!$validarFecha($fechaPublicacion)) {
        $errores[] = 'La fecha de publicación no es válida.';
    }
    if (!$validarFecha($fechaExpiracion)) {
        $errores[] = 'La fecha de expiración no es válida.';
    } elseif ($validarFecha($fechaPublicacion) && $fechaExpiracion <= $fechaPublicacion) {
        $errores[] = 'La fecha de expiración debe ser posterior a la de publicación.';
    }

    if (!$errores) {
        try {
            $conexion = conectarDB();
            $stmt = $conexion->prepare(
                'INSERT INTO novedades (titulo, texto, fechaPublicacion, fechaExpiracion)
                 VALUES (:titulo, :texto, :fechaPublicacion, :fechaExpiracion)'
            );
            $stmt->execute([
                'titulo' => $tituloNovedad,
                'texto' => $texto,
                'fechaPublicacion' => $fechaPublicacion,
                'fechaExpiracion' => $fechaExpiracion,
            ]);

            $mensajeOk = 'Novedad creada correctamente.';
            header('Location: ' . $urlListado . '?mensaje=' . rawurlencode($mensajeOk) . '&tipo=success');
            exit;
        } catch (PDOException $e) {
            error_log('VOLARA novedades: error al crear: ' . $e->getMessage());
            $errores[] = 'No se pudo crear la novedad. Verificá los datos e intentá nuevamente.';
        }
    }
}

require_once __DIR__ . '/../../../Includes/header.php';
require_once __DIR__ . '/../../../Includes/navbar.php';
?>

<main id="contenido-principal" class="management-page" tabindex="-1">
    <section class="page-header">
        <div class="container">
            <h1>Crear novedad</h1>
            <p class="text-muted mb-3">Completá los datos para publicar una nueva novedad.</p>
            <nav aria-label="Ruta de navegación">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= $escapar($baseUrl . '/Pages/Admin/index.php') ?>">Panel de administración</a></li>
                    <li class="breadcrumb-item"><a href="<?= $escapar($urlListado) ?>">Novedades</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Crear</li>
                </ol>
            </nav>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="mx-auto" style="max-width: 760px;">
                <?php if ($errores): ?>
                    <div class="volara-alert alert-danger mb-4" role="alert">
                        <strong>Revisá estos datos:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($errores as $error): ?><li><?= $escapar($error) ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= $escapar($baseUrl . '/Pages/Admin/novedades/crear.php') ?>" class="volara-card p-4" data-validate>
                    <input type="hidden" name="csrf_token" value="<?= $escapar(csrfToken()) ?>">

                    <div class="form-group mb-3">
                        <label for="titulo" class="volara-label">Título <span class="text-danger">*</span></label>
                        <input
                            type="text" id="titulo" name="titulo" class="volara-input"
                            minlength="3" maxlength="200" required
                            value="<?= $escapar($tituloNovedad) ?>"
                            autocomplete="off"
                        >
                        <small class="text-muted">Entre 3 y 200 caracteres.</small>
                    </div>

                    <div class="form-group mb-3">
                        <label for="texto" class="volara-label">Texto de la novedad <span class="text-danger">*</span></label>
                        <textarea id="texto" name="texto" class="volara-input" rows="7" maxlength="60000" required><?= $escapar($texto) ?></textarea>
                        <small class="text-muted">Escribí el contenido que se mostrará a los usuarios.</small>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="fechaPublicacion" class="volara-label">Fecha de publicación <span class="text-danger">*</span></label>
                            <input type="date" id="fechaPublicacion" name="fechaPublicacion" class="volara-input" value="<?= $escapar($fechaPublicacion) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="fechaExpiracion" class="volara-label">Fecha de expiración <span class="text-danger">*</span></label>
                            <input type="date" id="fechaExpiracion" name="fechaExpiracion" class="volara-input" value="<?= $escapar($fechaExpiracion) ?>" required>
                        </div>
                    </div>

                    <div class="d-flex flex-column flex-sm-row justify-content-end gap-2 mt-4">
                        <a href="<?= $escapar($urlListado) ?>" class="btn btn-volara-outline">Cancelar</a>
                        <button type="submit" class="btn btn-volara">
                            <i class="bi bi-check-lg me-2" aria-hidden="true"></i>Crear novedad
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../../../Includes/footer.php'; ?>
