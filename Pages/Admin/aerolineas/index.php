<?php
/**
 * Listado y baja de aerolíneas - VOLARA.
 * Ubicación: Pages/Admin/aerolineas/index.php
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../../Includes/functions.php';
require_once __DIR__ . '/../../../Includes/functionsF2.php';

$baseUrl = rtrim(BASE_URL, '/');
$urlListado = $baseUrl . '/Pages/Admin/aerolineas/index.php';
$escapar = static fn($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');

/* --------------------------------------------------------------------------
 * Autenticación y autorización: solo admin/administrador.
 * -------------------------------------------------------------------------- */
$usuarioActual = [];
if (!empty($_SESSION['codUsuario']) && function_exists('usuarioActual')) {
    $datosUsuario = usuarioActual();
    if (is_array($datosUsuario)) {
        $usuarioActual = $datosUsuario;
    }
}

$rol = trim((string) (
    $usuarioActual['rol']
    ?? $usuarioActual['tipoUsuario']
    ?? $_SESSION['tipoUsuario']
    ?? ''
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

$titulo = 'Gestión de aerolíneas';
$pageDescription = 'Consultá, creá, editá y administrá las aerolíneas registradas en VOLARA.';
$errores = [];
$mensaje = '';
$tipoMensaje = 'info';
$aerolineas = [];
$total = 0;
$paginationHTML = '';

/* --------------------------------------------------------------------------
 * Mensaje de resultado recibido por GET. El contenido siempre se escapa.
 * -------------------------------------------------------------------------- */
if (isset($_GET['mensaje']) && is_string($_GET['mensaje'])) {
    $mensaje = trim($_GET['mensaje']);
    $tipoSolicitado = is_string($_GET['tipo'] ?? null) ? $_GET['tipo'] : 'info';
    $tipoMensaje = in_array($tipoSolicitado, ['success', 'danger', 'warning', 'info'], true)
        ? $tipoSolicitado
        : 'info';
}

/* --------------------------------------------------------------------------
 * Eliminar: únicamente POST + CSRF y sin registros dependientes.
 * -------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = is_string($_POST['accion'] ?? null) ? $_POST['accion'] : '';
    $tokenRecibido = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null;
    $idRecibido = filter_var(
        $_POST['codAerolinea'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($accion !== 'eliminar' || $idRecibido === false || $idRecibido === null) {
        $mensaje = 'La solicitud de eliminación no es válida.';
        $tipoMensaje = 'danger';
    } elseif (!csrfValido($tokenRecibido)) {
        $mensaje = 'La sesión del formulario venció. Recargá la página e intentá nuevamente.';
        $tipoMensaje = 'danger';
    } else {
        try {
            $conexion = conectarDB();

            $stmtVuelos = $conexion->prepare(
                'SELECT COUNT(*) FROM vuelos WHERE codAerolinea = :codAerolinea'
            );
            $stmtVuelos->execute(['codAerolinea' => $idRecibido]);
            $cantidadVuelos = (int) $stmtVuelos->fetchColumn();

            $stmtPromociones = $conexion->prepare(
                'SELECT COUNT(*) FROM promociones WHERE codAerolinea = :codAerolinea'
            );
            $stmtPromociones->execute(['codAerolinea' => $idRecibido]);
            $cantidadPromociones = (int) $stmtPromociones->fetchColumn();

            // Algunas versiones del esquema vinculan usuarios CEO con codAerolinea.
            $columnaCeo = $conexion
                ->query("SHOW COLUMNS FROM usuarios LIKE 'codAerolinea'")
                ->fetch(PDO::FETCH_ASSOC);
            $cantidadUsuarios = 0;

            if ($columnaCeo) {
                $stmtUsuarios = $conexion->prepare(
                    'SELECT COUNT(*) FROM usuarios WHERE codAerolinea = :codAerolinea'
                );
                $stmtUsuarios->execute(['codAerolinea' => $idRecibido]);
                $cantidadUsuarios = (int) $stmtUsuarios->fetchColumn();
            }

            if ($cantidadVuelos > 0 || $cantidadPromociones > 0 || $cantidadUsuarios > 0) {
                $partes = [];
                if ($cantidadVuelos > 0) {
                    $partes[] = $cantidadVuelos . ' vuelo(s)';
                }
                if ($cantidadPromociones > 0) {
                    $partes[] = $cantidadPromociones . ' promoción(es)';
                }
                if ($cantidadUsuarios > 0) {
                    $partes[] = $cantidadUsuarios . ' usuario(s) asociado(s)';
                }
                $mensaje = 'No se puede eliminar esta aerolínea porque tiene registros asociados: '
                    . implode(', ', $partes) . '. Primero resolvé esas dependencias.';
                $tipoMensaje = 'warning';
            } else {
                $stmtEliminar = $conexion->prepare(
                    'DELETE FROM aerolineas WHERE codAerolinea = :codAerolinea'
                );
                $stmtEliminar->execute(['codAerolinea' => $idRecibido]);

                if ($stmtEliminar->rowCount() === 1) {
                    $mensaje = 'Aerolínea eliminada correctamente.';
                    $tipoMensaje = 'success';
                } else {
                    $mensaje = 'La aerolínea no existe o ya fue eliminada.';
                    $tipoMensaje = 'warning';
                }
            }
        } catch (PDOException $e) {
            error_log('VOLARA - Error al eliminar aerolínea: ' . $e->getMessage());
            $mensaje = 'No se pudo eliminar la aerolínea. Puede tener otros registros asociados.';
            $tipoMensaje = 'danger';
        }
    }

    // Patrón POST/Redirect/GET: evita repetir el envío al actualizar el navegador.
    header(
        'Location: ' . $urlListado
        . '?mensaje=' . rawurlencode($mensaje)
        . '&tipo=' . rawurlencode($tipoMensaje)
    );
    exit;
}

/* --------------------------------------------------------------------------
 * Listado paginado.
 * -------------------------------------------------------------------------- */
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = 10;

try {
    $conexion = conectarDB();
    $total = (int) $conexion->query('SELECT COUNT(*) FROM aerolineas')->fetchColumn();
    $paginacion = paginar($total, $porPagina, $pagina);
    $pagina = $paginacion['paginaActual'];

    $stmt = $conexion->prepare(
        'SELECT codAerolinea, codigoIATA, nombre, pais
         FROM aerolineas
         ORDER BY nombre ASC
         LIMIT :limite OFFSET :offset'
    );
    $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $paginacion['offset'], PDO::PARAM_INT);
    $stmt->execute();
    $aerolineas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $paginationHTML = generarPaginacionHTML(
        $total,
        $porPagina,
        $pagina,
        $urlListado
    );
} catch (PDOException $e) {
    error_log('VOLARA - Error al listar aerolíneas: ' . $e->getMessage());
    $errores[] = 'No se pudieron cargar las aerolíneas. Revisá la conexión con la base de datos.';
}

require_once __DIR__ . '/../../../Includes/header.php';
require_once __DIR__ . '/../../../Includes/navbar.php';
?>

<main id="contenido-principal" class="py-5" tabindex="-1">
    <section class="page-header">
        <div class="container">
            <h1>Gestión de aerolíneas</h1>
            <p class="text-muted mb-3">Administrá las aerolíneas registradas en VOLARA.</p>
            <nav aria-label="Ruta de navegación">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="<?= $escapar($baseUrl . '/Pages/Admin/index.php') ?>">Panel de administración</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Aerolíneas</li>
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
                    <?= $escapar(implode(' ', $errores)) ?>
                </div>
            <?php endif; ?>

            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <div>
                    <h2 class="section-title mb-1">Aerolíneas registradas</h2>
                    <p class="text-muted mb-0"><?= number_format($total, 0, ',', '.') ?> registro(s)</p>
                </div>
                <a href="<?= $escapar($baseUrl . '/Pages/Admin/aerolineas/crear.php') ?>" class="btn btn-volara">
                    <i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Crear aerolínea
                </a>
            </div>

            <?php if ($aerolineas): ?>
                <div class="volara-card p-0 overflow-hidden">
                    <div class="volara-table-responsive" role="region" aria-label="Listado de aerolíneas" tabindex="0">
                        <table class="volara-table mb-0">
                            <caption class="visually-hidden">Aerolíneas registradas en VOLARA</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Código IATA</th>
                                    <th scope="col">Nombre</th>
                                    <th scope="col">País</th>
                                    <th scope="col" class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($aerolineas as $aerolinea): ?>
                                    <tr>
                                        <td><span class="volara-badge badge-neutral"><?= $escapar($aerolinea['codigoIATA'] ?? '') ?></span></td>
                                        <td><strong><?= $escapar($aerolinea['nombre'] ?? '') ?></strong></td>
                                        <td><?= $escapar($aerolinea['pais'] ?? '') ?></td>
                                        <td class="text-end">
                                            <div class="d-inline-flex flex-wrap justify-content-end gap-2">
                                                <a
                                                    href="<?= $escapar($baseUrl . '/Pages/Admin/aerolineas/editar.php?cod=' . (int) $aerolinea['codAerolinea']) ?>"
                                                    class="btn btn-sm btn-outline-primary"
                                                    aria-label="Editar <?= $escapar($aerolinea['nombre'] ?? '') ?>"
                                                >
                                                    <i class="bi bi-pencil me-1" aria-hidden="true"></i>Editar
                                                </a>
                                                <form method="post" action="<?= $escapar($urlListado) ?>" onsubmit="return confirm('¿Seguro que querés eliminar esta aerolínea? Esta acción no se puede deshacer.');">
                                                    <input type="hidden" name="csrf_token" value="<?= $escapar(csrfToken()) ?>">
                                                    <input type="hidden" name="codAerolinea" value="<?= (int) $aerolinea['codAerolinea'] ?>">
                                                    <button type="submit" name="accion" value="eliminar" class="btn btn-sm btn-outline-danger">
                                                        <i class="bi bi-trash me-1" aria-hidden="true"></i>Eliminar
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if ($paginationHTML !== ''): ?>
                    <div class="volara-pagination-container mt-4">
                        <div class="volara-pagination">
                            <?= $paginationHTML ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="volara-card-flat text-center py-5">
                    <i class="bi bi-airplane empty-state-icon" aria-hidden="true"></i>
                    <h2 class="h4 mt-3">No hay aerolíneas registradas</h2>
                    <p class="text-muted">Cuando crees una aerolínea, aparecerá en este listado.</p>
                    <a href="<?= $escapar($baseUrl . '/Pages/Admin/aerolineas/crear.php') ?>" class="btn btn-volara">
                        <i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Crear primera aerolínea
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../../../Includes/footer.php'; ?>
