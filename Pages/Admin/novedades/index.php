<?php
/**
 * Listado y eliminación de novedades de VOLARA.
 * Ubicación: Pages/Admin/novedades/index.php
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../../Includes/functions.php';
require_once __DIR__ . '/../../../Includes/functionsF2.php';

$baseUrl = rtrim(BASE_URL, '/');
$urlListado = $baseUrl . '/Pages/Admin/novedades/index.php';
$urlCrear = $baseUrl . '/Pages/Admin/novedades/crear.php';

$escapar = static function ($valor): string {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
};

/* --------------------------------------------------------------------------
 * Control de acceso: únicamente admin/administrador.
 * -------------------------------------------------------------------------- */
$datosUsuario = [];
if (function_exists('usuarioActual')) {
    $usuarioConsultado = usuarioActual();
    if (is_array($usuarioConsultado)) {
        $datosUsuario = $usuarioConsultado;
    }
}

$rol = trim((string) (
    $datosUsuario['rol']
    ?? $datosUsuario['tipoUsuario']
    ?? $_SESSION['tipoUsuario']
    ?? ''
));
$rol = function_exists('mb_strtolower')
    ? mb_strtolower($rol, 'UTF-8')
    : strtolower($rol);
$rol = strtr($rol, [
    'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u'
]);

if (empty($_SESSION['codUsuario'])) {
    header('Location: ' . $baseUrl . '/Auth/login.php');
    exit;
}
if (!in_array($rol, ['admin', 'administrador'], true)) {
    header('Location: ' . $baseUrl . '/Pages/Publico/index.php');
    exit;
}

$titulo = 'Gestión de novedades';
$pageDescription = 'Administrá las novedades publicadas en VOLARA.';
$errores = [];
$mensaje = '';
$tipoMensaje = 'info';
$novedades = [];
$total = 0;
$conexion = null;

/* --------------------------------------------------------------------------
 * Mensajes recibidos tras una redirección.
 * -------------------------------------------------------------------------- */
if (isset($_GET['mensaje'])) {
    $mensaje = trim((string) $_GET['mensaje']);
    $tipoRecibido = (string) ($_GET['tipo'] ?? 'info');
    $tipoMensaje = in_array($tipoRecibido, ['success', 'danger', 'warning', 'info'], true)
        ? $tipoRecibido
        : 'info';
}

/* --------------------------------------------------------------------------
 * Eliminar: solo POST + CSRF.
 * -------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $idRecibido = filter_var(
        $_POST['codNovedad'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    $token = $_POST['csrf_token'] ?? null;

    if ($accion !== 'eliminar' || $idRecibido === false || $idRecibido === null) {
        $errores[] = 'La solicitud de eliminación no es válida.';
    } elseif (!is_string($token) || !csrfValido($token)) {
        $errores[] = 'La sesión del formulario venció. Recargá la página e intentá nuevamente.';
    } else {
        try {
            $conexion = conectarDB();
            $stmtEliminar = $conexion->prepare(
                'DELETE FROM novedades WHERE codNovedad = :codNovedad'
            );
            $stmtEliminar->bindValue(':codNovedad', (int) $idRecibido, PDO::PARAM_INT);
            $stmtEliminar->execute();

            if ($stmtEliminar->rowCount() > 0) {
                $texto = 'Novedad eliminada correctamente.';
                header('Location: ' . $urlListado . '?mensaje=' . rawurlencode($texto) . '&tipo=success');
                exit;
            }

            $errores[] = 'La novedad no existe o ya fue eliminada.';
        } catch (PDOException $e) {
            error_log('VOLARA novedades: error al eliminar: ' . $e->getMessage());
            $errores[] = 'No se pudo eliminar la novedad. Intentá nuevamente.';
        }
    }
}

/* --------------------------------------------------------------------------
 * Listado paginado.
 * -------------------------------------------------------------------------- */
$paginaSolicitada = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = 10;
$paginacion = paginar(0, $porPagina, $paginaSolicitada);

try {
    if ($conexion === null) {
        $conexion = conectarDB();
    }

    $stmtCount = $conexion->query('SELECT COUNT(*) AS total FROM novedades');
    $total = (int) $stmtCount->fetchColumn();
    $paginacion = paginar($total, $porPagina, $paginaSolicitada);
    $pagina = $paginacion['paginaActual'];

    $stmtListado = $conexion->prepare(
        'SELECT codNovedad, titulo, texto, fechaPublicacion, fechaExpiracion
         FROM novedades
         ORDER BY fechaPublicacion DESC, codNovedad DESC
         LIMIT :limite OFFSET :offset'
    );
    $stmtListado->bindValue(':limite', $porPagina, PDO::PARAM_INT);
    $stmtListado->bindValue(':offset', $paginacion['offset'], PDO::PARAM_INT);
    $stmtListado->execute();
    $novedades = $stmtListado->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('VOLARA novedades: error al cargar listado: ' . $e->getMessage());
    $errores[] = 'No se pudieron cargar las novedades. Verificá la conexión con la base de datos.';
    $novedades = [];
}

$paginationHTML = generarPaginacionHTML(
    $total,
    $porPagina,
    (int) ($paginacion['paginaActual'] ?? 1),
    $urlListado
);

require_once __DIR__ . '/../../../Includes/header.php';
require_once __DIR__ . '/../../../Includes/navbar.php';
?>

<main id="contenido-principal" class="management-page" tabindex="-1">
    <section class="page-header">
        <div class="container">
            <h1>Gestión de novedades</h1>
            <p class="text-muted mb-3">Administrá las novedades que se publican en VOLARA.</p>
            <nav aria-label="Ruta de navegación">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="<?= $escapar($baseUrl . '/Pages/Admin/index.php') ?>">Panel de administración</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Novedades</li>
                </ol>
            </nav>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <?php if ($mensaje !== ''): ?>
                <div class="volara-alert alert-<?= $escapar($tipoMensaje) ?> mb-3" role="status">
                    <?= $escapar($mensaje) ?>
                </div>
            <?php endif; ?>

            <?php if ($errores): ?>
                <div class="volara-alert alert-danger mb-3" role="alert">
                    <strong>No se pudo completar la operación.</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errores as $error): ?>
                            <li><?= $escapar($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <div>
                    <h2 class="section-title mb-1">Novedades registradas</h2>
                    <p class="text-muted mb-0"><?= $total ?> <?= $total === 1 ? 'registro' : 'registros' ?></p>
                </div>
                <a href="<?= $escapar($urlCrear) ?>" class="btn btn-volara">
                    <i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Crear novedad
                </a>
            </div>

            <?php if ($novedades): ?>
                <div class="volara-table-responsive" role="region" aria-label="Listado de novedades" tabindex="0">
                    <table class="volara-table">
                        <caption class="visually-hidden">Novedades registradas en VOLARA</caption>
                        <thead>
                            <tr>
                                <th scope="col">Título</th>
                                <th scope="col">Publicación</th>
                                <th scope="col">Expiración</th>
                                <th scope="col" class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($novedades as $novedad): ?>
                                <tr>
                                    <td>
                                        <strong><?= $escapar($novedad['titulo'] ?? '') ?></strong>
                                        <?php if (!empty($novedad['texto'])): ?>
                                            <small class="d-block text-muted">
                                                <?= $escapar(function_exists('mb_strimwidth')
                                                    ? mb_strimwidth((string) $novedad['texto'], 0, 100, '…', 'UTF-8')
                                                    : substr((string) $novedad['texto'], 0, 100)) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $fechaPublicacion = !empty($novedad['fechaPublicacion'])
                                            ? strtotime((string) $novedad['fechaPublicacion'])
                                            : false;
                                        echo $fechaPublicacion !== false
                                            ? $escapar(date('d/m/Y', $fechaPublicacion))
                                            : '—';
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $fechaExpiracion = !empty($novedad['fechaExpiracion'])
                                            ? strtotime((string) $novedad['fechaExpiracion'])
                                            : false;
                                        echo $fechaExpiracion !== false
                                            ? $escapar(date('d/m/Y', $fechaExpiracion))
                                            : '—';
                                        ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="table-actions justify-content-end">
                                            <a
                                                class="table-action-btn"
                                                href="<?= $escapar($baseUrl . '/Pages/Admin/novedades/editar.php?cod=' . (int) $novedad['codNovedad']) ?>"
                                                aria-label="Editar <?= $escapar($novedad['titulo'] ?? '') ?>"
                                                title="Editar"
                                            >
                                                <i class="bi bi-pencil" aria-hidden="true"></i>
                                            </a>
                                            <form
                                                method="POST"
                                                action="<?= $escapar($urlListado) ?>"
                                                onsubmit="return confirm('¿Seguro que querés eliminar esta novedad? Esta acción no se puede deshacer.');"
                                            >
                                                <input type="hidden" name="csrf_token" value="<?= $escapar(csrfToken()) ?>">
                                                <input type="hidden" name="codNovedad" value="<?= (int) $novedad['codNovedad'] ?>">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <button
                                                    type="submit"
                                                    class="table-action-btn danger"
                                                    aria-label="Eliminar <?= $escapar($novedad['titulo'] ?? '') ?>"
                                                    title="Eliminar"
                                                >
                                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total > $porPagina): ?>
                    <div class="d-flex justify-content-center mt-4">
                        <?= $paginationHTML ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="volara-card-flat text-center py-5">
                    <i class="bi bi-newspaper empty-state-icon" aria-hidden="true"></i>
                    <h3 class="h4 mt-3">No hay novedades registradas</h3>
                    <p class="text-muted">Creá una novedad para empezar a publicar información en VOLARA.</p>
                    <a href="<?= $escapar($urlCrear) ?>" class="btn btn-volara">
                        <i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Crear primera novedad
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../../../Includes/footer.php'; ?>
