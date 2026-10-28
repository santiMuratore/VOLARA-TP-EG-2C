<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../../Includes/functions.php';
require_once __DIR__ . '/../../../Includes/functionsF2.php';

$baseUrl = rtrim(BASE_URL, '/');
$urlListado = $baseUrl . '/Pages/Admin/aerolineas/index.php';
$urlCrear = $baseUrl . '/Pages/Admin/aerolineas/crear.php';

$escapar = static function ($valor): string {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
};

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
    'á' => 'a',
    'é' => 'e',
    'í' => 'i',
    'ó' => 'o',
    'ú' => 'u',
    'ü' => 'u'
]);

if (empty($_SESSION['codUsuario'])) {
    header('Location: ' . $baseUrl . '/Auth/login.php');
    exit;
}

if (!in_array($rol, ['admin', 'administrador'], true)) {
    header('Location: ' . $baseUrl . '/Pages/Publico/index.php');
    exit;
}

$titulo = 'Gestión de aerolíneas';
$pageDescription = 'Administrá las aerolíneas registradas en VOLARA.';

$errores = [];
$mensaje = '';
$tipoMensaje = 'info';
$aerolineas = [];
$total = 0;
$conexion = null;

if (isset($_GET['mensaje']) && is_string($_GET['mensaje'])) {
    $mensaje = trim($_GET['mensaje']);
    $tipoRecibido = is_string($_GET['tipo'] ?? null)
        ? $_GET['tipo']
        : 'info';

    $tipoMensaje = in_array(
        $tipoRecibido,
        ['success', 'danger', 'warning', 'info'],
        true
    ) ? $tipoRecibido : 'info';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = is_string($_POST['accion'] ?? null)
        ? $_POST['accion']
        : '';

    $token = is_string($_POST['csrf_token'] ?? null)
        ? $_POST['csrf_token']
        : null;

    $idRecibido = filter_var(
        $_POST['codAerolinea'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($accion !== 'eliminar' || $idRecibido === false || $idRecibido === null) {
        $mensaje = 'La solicitud de eliminación no es válida.';
        $tipoMensaje = 'danger';
    } elseif (!csrfValido($token)) {
        $mensaje = 'La sesión del formulario venció. Recargá la página e intentá nuevamente.';
        $tipoMensaje = 'danger';
    } else {
        try {
            $conexion = conectarDB();

            $stmtVuelos = $conexion->prepare(
                'SELECT COUNT(*) FROM vuelos WHERE codAerolinea = :codAerolinea'
            );
            $stmtVuelos->execute([
                'codAerolinea' => $idRecibido
            ]);
            $cantidadVuelos = (int) $stmtVuelos->fetchColumn();

            $stmtPromociones = $conexion->prepare(
                'SELECT COUNT(*) FROM promociones WHERE codAerolinea = :codAerolinea'
            );
            $stmtPromociones->execute([
                'codAerolinea' => $idRecibido
            ]);
            $cantidadPromociones = (int) $stmtPromociones->fetchColumn();

            $columnaCeo = $conexion
                ->query("SHOW COLUMNS FROM usuarios LIKE 'codAerolinea'")
                ->fetch(PDO::FETCH_ASSOC);

            $cantidadUsuarios = 0;

            if ($columnaCeo) {
                $stmtUsuarios = $conexion->prepare(
                    'SELECT COUNT(*) FROM usuarios WHERE codAerolinea = :codAerolinea'
                );
                $stmtUsuarios->execute([
                    'codAerolinea' => $idRecibido
                ]);
                $cantidadUsuarios = (int) $stmtUsuarios->fetchColumn();
            }

            if (
                $cantidadVuelos > 0
                || $cantidadPromociones > 0
                || $cantidadUsuarios > 0
            ) {
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
                    . implode(', ', $partes)
                    . '. Primero resolvé esas dependencias.';

                $tipoMensaje = 'warning';
            } else {
                $stmtEliminar = $conexion->prepare(
                    'DELETE FROM aerolineas WHERE codAerolinea = :codAerolinea'
                );

                $stmtEliminar->execute([
                    'codAerolinea' => $idRecibido
                ]);

                if ($stmtEliminar->rowCount() === 1) {
                    $mensaje = 'Aerolínea eliminada correctamente.';
                    $tipoMensaje = 'success';
                } else {
                    $mensaje = 'La aerolínea no existe o ya fue eliminada.';
                    $tipoMensaje = 'warning';
                }
            }
        } catch (PDOException $e) {
            error_log(
                'VOLARA - Error al eliminar aerolínea: ' . $e->getMessage()
            );

            $mensaje = 'No se pudo eliminar la aerolínea. Puede tener otros registros asociados.';
            $tipoMensaje = 'danger';
        }
    }

    header(
        'Location: ' . $urlListado
            . '?mensaje=' . rawurlencode($mensaje)
            . '&tipo=' . rawurlencode($tipoMensaje)
    );
    exit;
}

$paginaSolicitada = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = 10;
$paginacion = paginar(0, $porPagina, $paginaSolicitada);

try {
    $conexion = conectarDB();

    $stmtCount = $conexion->query(
        'SELECT COUNT(*) AS total FROM aerolineas'
    );

    $total = (int) $stmtCount->fetchColumn();

    $paginacion = paginar($total, $porPagina, $paginaSolicitada);
    $pagina = $paginacion['paginaActual'];

    $stmtListado = $conexion->prepare(
        'SELECT codAerolinea, codigoIATA, nombre, pais
         FROM aerolineas
         ORDER BY nombre ASC
         LIMIT :limite OFFSET :offset'
    );

    $stmtListado->bindValue(':limite', $porPagina, PDO::PARAM_INT);
    $stmtListado->bindValue(':offset', $paginacion['offset'], PDO::PARAM_INT);
    $stmtListado->execute();

    $aerolineas = $stmtListado->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log(
        'VOLARA - Error al cargar aerolíneas: ' . $e->getMessage()
    );

    $errores[] = 'No se pudieron cargar las aerolíneas. Verificá la conexión con la base de datos.';
    $aerolineas = [];
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

            <h1>Gestión de aerolíneas</h1>

            <p class="text-muted mb-3">
                Administrá las aerolíneas registradas en VOLARA.
            </p>

            <nav aria-label="Ruta de navegación">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="<?= $escapar($baseUrl . '/Pages/Publico/index.php') ?>">
                            Inicio
                        </a>
                    </li>

                    <li class="breadcrumb-item active" aria-current="page">
                        Aerolíneas
                    </li>
                </ol>
            </nav>

        </div>
    </section>

    <section class="section">
        <div class="container">

            <?php if ($mensaje !== ''): ?>
                <div
                    class="volara-alert alert-<?= $escapar($tipoMensaje) ?> mb-3"
                    role="status">
                    <?= $escapar($mensaje) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errores)): ?>
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
                    <h2 class="section-title mb-1">
                        Aerolíneas registradas
                    </h2>

                    <p class="text-muted mb-0">
                        <?= number_format($total, 0, ',', '.') ?>
                        <?= $total === 1 ? 'registro' : 'registros' ?>
                    </p>
                </div>

                <a href="<?= $escapar($urlCrear) ?>" class="btn btn-volara">
                    <i class="bi bi-plus-lg me-2" aria-hidden="true"></i>
                    Crear aerolínea
                </a>

            </div>

            <?php if ($aerolineas): ?>

                <div
                    class="volara-table-responsive"
                    role="region"
                    aria-label="Listado de aerolíneas"
                    tabindex="0">
                    <table class="volara-table">
                        <caption class="visually-hidden">
                            Aerolíneas registradas en VOLARA
                        </caption>

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
                                    <td>
                                        <span class="volara-badge badge-neutral">
                                            <?= $escapar($aerolinea['codigoIATA'] ?? '') ?>
                                        </span>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= $escapar($aerolinea['nombre'] ?? '') ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= $escapar($aerolinea['pais'] ?? '') ?>
                                    </td>

                                    <td class="text-end">
                                        <div class="table-actions justify-content-end">

                                            <a
                                                class="table-action-btn"
                                                href="<?= $escapar(
                                                            $baseUrl
                                                                . '/Pages/Admin/aerolineas/editar.php?cod='
                                                                . (int) $aerolinea['codAerolinea']
                                                        ) ?>"
                                                aria-label="Editar <?= $escapar($aerolinea['nombre'] ?? '') ?>"
                                                title="Editar">
                                                <i class="bi bi-pencil" aria-hidden="true"></i>
                                            </a>

                                            <form
                                                method="POST"
                                                action="<?= $escapar($urlListado) ?>"
                                                onsubmit="return confirm('¿Seguro que querés eliminar esta aerolínea? Esta acción no se puede deshacer.');">
                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= $escapar(csrfToken()) ?>">

                                                <input
                                                    type="hidden"
                                                    name="codAerolinea"
                                                    value="<?= (int) $aerolinea['codAerolinea'] ?>">

                                                <input
                                                    type="hidden"
                                                    name="accion"
                                                    value="eliminar">

                                                <button
                                                    type="submit"
                                                    class="table-action-btn danger"
                                                    aria-label="Eliminar <?= $escapar($aerolinea['nombre'] ?? '') ?>"
                                                    title="Eliminar">
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
                    <div class="volara-pagination-container mt-4">
                        <div class="volara-pagination">
                            <?= $paginationHTML ?>
                        </div>
                    </div>
                <?php endif; ?>


            <?php else: ?>

                <div class="volara-card-flat text-center py-5">

                    <i
                        class="bi bi-airplane empty-state-icon"
                        aria-hidden="true"></i>

                    <h3 class="h4 mt-3">
                        No hay aerolíneas registradas
                    </h3>

                    <p class="text-muted">
                        Creá una aerolínea para empezar a gestionar los vuelos en VOLARA.
                    </p>

                    <a href="<?= $escapar($urlCrear) ?>" class="btn btn-volara">
                        <i class="bi bi-plus-lg me-2" aria-hidden="true"></i>
                        Crear primera aerolínea
                    </a>

                </div>

            <?php endif; ?>

        </div>
    </section>

</main>

<?php require_once __DIR__ . '/../../../Includes/footer.php'; ?>