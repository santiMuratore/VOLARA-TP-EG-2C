<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Includes compartidos: igual que en vuelos.php
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../../Includes/functions.php';
require_once __DIR__ . '/../../Includes/functionsF2.php';

$baseUrl = rtrim(BASE_URL, '/');
$urlHistorial = $baseUrl . '/Pages/Pasajero/historial.php';

/*
|--------------------------------------------------------------------------
| Verificar sesión y rol
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['codUsuario'])) {
    header('Location: ' . $baseUrl . '/Auth/login.php');
    exit;
}

$rolUsuario = trim((string) (
    $_SESSION['tipoUsuario'] ?? $_SESSION['rol'] ?? ''
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

if (!in_array($rolUsuario, ['usuario', 'pasajero', 'cliente'], true)) {
    header('Location: ' . $baseUrl . '/Pages/Publico/index.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Configuración
|--------------------------------------------------------------------------
*/

$titulo = 'Historial de reservas';
$pageDescription = 'Consultá tus reservas confirmadas y canceladas en VOLARA.';
$codUsuario = (int) $_SESSION['codUsuario'];
$porPagina = 4;
$paginaSolicitada = max(1, (int) ($_GET['pagina'] ?? 1));

$historial = [];
$totalHistorial = 0;
$totalPaginas = 1;
$paginaHistorial = 1;
$offset = 0;
$error = '';

/*
|--------------------------------------------------------------------------
| Consultar historial y paginar
|--------------------------------------------------------------------------
| El schema.sql define los estados confirmada, cancelada y pendiente de pago.
| El historial muestra únicamente las confirmadas y las canceladas.
|--------------------------------------------------------------------------
*/

try {
    $conexion = conectarDB();

    if (!$conexion instanceof PDO) {
        throw new RuntimeException('La conexión no devolvió un objeto PDO válido.');
    }

    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Contar todos los movimientos del pasajero.
    $sqlConteo = "
        SELECT COUNT(*)
        FROM reservas r
        WHERE r.codUsuario = :codUsuario
          AND r.estadoReserva IN ('confirmada', 'cancelada')
    ";

    $stmtConteo = $conexion->prepare($sqlConteo);
    $stmtConteo->bindValue(':codUsuario', $codUsuario, PDO::PARAM_INT);
    $stmtConteo->execute();
    $totalHistorial = (int) $stmtConteo->fetchColumn();

    // 2. Calcular páginas y offset con la función ya usada en vuelos.php.
    $datosPaginacion = paginar($totalHistorial, $porPagina, $paginaSolicitada);
    $paginaHistorial = (int) $datosPaginacion['paginaActual'];
    $totalPaginas = (int) $datosPaginacion['totalPaginas'];
    $offset = (int) $datosPaginacion['offset'];

    // 3. Consultar exclusivamente los registros de la página actual.
    $sqlHistorial = "
        SELECT
            r.codReserva,
            r.estadoReserva,
            r.precioFinal,
            r.asiento,
            r.fechaReserva,
            r.fechaPago,
            r.fechaCancelacion,
            v.codigoVuelo,
            v.origen,
            v.destino,
            v.fecha AS fechaVuelo,
            v.hora AS horaVuelo,
            v.clase,
            a.nombre AS nombreAerolinea,
            COALESCE(
                CASE
                    WHEN r.estadoReserva = 'cancelada' THEN r.fechaCancelacion
                    WHEN r.estadoReserva = 'confirmada' THEN r.fechaPago
                    ELSE NULL
                END,
                r.fechaReserva
            ) AS fechaMovimiento
        FROM reservas r
        LEFT JOIN vuelos v
            ON v.codVuelo = r.codVuelo
        LEFT JOIN aerolineas a
            ON a.codAerolinea = v.codAerolinea
        WHERE r.codUsuario = :codUsuario
          AND r.estadoReserva IN ('confirmada', 'cancelada')
        ORDER BY fechaMovimiento DESC, r.codReserva DESC
        LIMIT :limite OFFSET :offset
    ";

    $stmtHistorial = $conexion->prepare($sqlHistorial);
    $stmtHistorial->bindValue(':codUsuario', $codUsuario, PDO::PARAM_INT);
    $stmtHistorial->bindValue(':limite', $porPagina, PDO::PARAM_INT);
    $stmtHistorial->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtHistorial->execute();

    $historial = $stmtHistorial->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('VOLARA - Error cargando historial: ' . $e->getMessage());
    $error = 'No se pudo cargar tu historial. Intentá nuevamente más tarde.';
}

/*
|--------------------------------------------------------------------------
| Enlaces de paginación manuales
|--------------------------------------------------------------------------
| No se llama a generarPaginacionHTML(), para evitar confundir el orden
| de sus argumentos. Los enlaces conservan el parámetro pagina.
|--------------------------------------------------------------------------
*/

$urlPagina = static function (int $numeroPagina) use ($urlHistorial): string {
    return $urlHistorial . '?' . http_build_query([
        'pagina' => max(1, $numeroPagina),
    ]);
};

// Mismas plantillas compartidas que en vuelos.php y mis-reservas.php.
require_once __DIR__ . '/../../Includes/header.php';
require_once __DIR__ . '/../../Includes/navbar.php';
?>

<style>
    .volara-history-list {
        display: grid;
        gap: 1rem;
    }

    .volara-history-card {
        padding: 1.35rem;
        border: 1px solid #DDE2E7;
        border-radius: 16px;
        background: #FFFFFF;
        box-shadow: 0 3px 14px rgba(10, 14, 19, .04);
    }

    .volara-history-card-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
    }

    .volara-history-route {
        font-size: 1.2rem;
        font-weight: 700;
        overflow-wrap: anywhere;
    }

    .volara-history-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .65rem 1.25rem;
        margin-top: 1rem;
        color: #6B7280;
        font-size: .92rem;
    }

    .volara-history-meta span {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
    }

    .volara-history-footer {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: .75rem;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid #E5E7EB;
    }

    .volara-history-price {
        color: #151D25;
        font-size: 1.2rem;
        font-weight: 750;
    }
    .volara-history-pagination-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        width: 100%;
        margin: 1.75rem 0 .5rem;
    }


    .volara-history-pagination {
        max-width: 100%;
        overflow-x: auto;
    }

    .volara-history-pagination .pagination {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        align-items: center;
        gap: .35rem;
        margin: 0;
        padding: 0;
    }

    .volara-history-pagination .page-link {
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        min-height: 36px;
        padding: .4rem .6rem;
        border: 1px solid #DDE2E7;
        border-radius: 8px !important;
        background: #FFFFFF;
        color: #374151;
        font-size: .875rem;
        font-weight: 600;
    }

    .volara-history-pagination .page-link:hover {
        border-color: #E51B23;
        background: #FDEAEB;
        color: #C9141C;
    }

    .volara-history-pagination .page-item.active .page-link {
        border-color: #E51B23;
        background: #E51B23;
        color: #FFFFFF;
    }

    .volara-history-pagination .page-item.disabled .page-link {
        background: #F4F6F8;
        color: #9CA3AF;
        cursor: not-allowed;
    }

    .volara-history-pagination .page-link:focus-visible {
        outline: 3px solid rgba(229, 27, 35, .25);
        outline-offset: 2px;
    }

    .volara-history-pagination-info {
        margin-top: .85rem;
        text-align: center;
        color: #6B7280;
        font-size: .875rem;
    }

    @media (max-width: 576px) {
        .volara-history-card {
            padding: 1rem;
        }

        .volara-history-card-top {
            flex-direction: column;
        }

        .volara-history-pagination .pagination {
            gap: .25rem;
        }

        .volara-history-pagination .page-link {
            min-width: 31px;
            min-height: 31px;
            padding: .3rem .45rem;
            font-size: .8rem;
        }
    }
</style>

<main id="contenido-principal" class="volara-public-page volara-pasajero-page" tabindex="-1">
    <section class="page-header">
        <div class="container">
            <h1>Historial de reservas</h1>
            <p class="text-muted mb-3">
                Consultá tus reservas confirmadas y canceladas, los vuelos y los importes registrados.
            </p>

            <nav aria-label="Ruta de navegación">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="<?= htmlspecialchars($baseUrl . '/Pages/Publico/index.php', ENT_QUOTES, 'UTF-8') ?>">Inicio</a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="<?= htmlspecialchars($baseUrl . '/Pages/Pasajero/mis-reservas.php', ENT_QUOTES, 'UTF-8') ?>">Mis reservas</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Historial</li>
                </ol>
            </nav>
        </div>
    </section>

    <section class="volara-public-section">
        <div class="container">
            <?php if ($error !== ''): ?>
                <div class="volara-alert alert-danger mb-4" role="alert">
                    <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                <div>
                    <h2 class="h4 mb-1">Movimientos registrados</h2>
                    <p class="text-muted mb-0">
                        <?= $totalHistorial ?>
                        <?= $totalHistorial === 1 ? 'reserva en el historial' : 'reservas en el historial' ?>
                    </p>
                </div>

                <a href="<?= htmlspecialchars($baseUrl . '/Pages/Pasajero/mis-reservas.php', ENT_QUOTES, 'UTF-8') ?>" class="btn btn-volara-outline">
                    <i class="bi bi-ticket-perforated me-2" aria-hidden="true"></i>
                    Ver mis reservas
                </a>
            </div>

            <?php if ($error === '' && empty($historial)): ?>
                <div class="volara-card text-center py-5">
                    <div class="stat-icon stat-icon-red mx-auto mb-3">
                        <i class="bi bi-clock-history" aria-hidden="true"></i>
                    </div>
                    <h2 class="h4">Todavía no hay movimientos en tu historial</h2>
                    <p class="text-muted mb-4">
                        Las reservas confirmadas o canceladas aparecerán aquí.
                    </p>
                    <a href="<?= htmlspecialchars($baseUrl . '/Pages/Pasajero/mis-reservas.php', ENT_QUOTES, 'UTF-8') ?>" class="btn btn-volara">
                        Ver mis reservas
                    </a>
                </div>
            <?php elseif (!empty($historial)): ?>
                <div class="volara-history-list">
                    <?php foreach ($historial as $reserva): ?>
                        <?php
                        $estado = strtolower(trim((string) ($reserva['estadoReserva'] ?? '')));
                        $esCancelada = ($estado === 'cancelada');
                        $etiquetaEstado = $esCancelada ? 'Cancelada' : 'Confirmada';
                        $claseEstado = $esCancelada ? 'danger' : 'success';

                        $fechaVuelo = 'Fecha no disponible';
                        if (!empty($reserva['fechaVuelo'])) {
                            $timestampVuelo = strtotime((string) $reserva['fechaVuelo']);
                            if ($timestampVuelo !== false) {
                                $fechaVuelo = date('d/m/Y', $timestampVuelo);
                            }
                        }

                        $horaVuelo = !empty($reserva['horaVuelo'])
                            ? substr((string) $reserva['horaVuelo'], 0, 5)
                            : 'No disponible';

                        $asiento = trim((string) ($reserva['asiento'] ?? ''));
                        $precio = is_numeric($reserva['precioFinal'] ?? null)
                            ? (float) $reserva['precioFinal']
                            : null;

                        $fechaMovimiento = !empty($reserva['fechaMovimiento'])
                            ? strtotime((string) $reserva['fechaMovimiento'])
                            : false;
                        $fechaMovimientoTexto = $fechaMovimiento !== false
                            ? date('d/m/Y H:i', $fechaMovimiento)
                            : 'No disponible';
                        ?>

                        <article class="volara-history-card">
                            <div class="volara-history-card-top">
                                <div>
                                    <p class="eyebrow mb-1">
                                        <?= htmlspecialchars((string) ($reserva['nombreAerolinea'] ?? 'Aerolínea'), ENT_QUOTES, 'UTF-8') ?>
                                        <span aria-hidden="true">·</span>
                                        <?= htmlspecialchars((string) ($reserva['codigoVuelo'] ?? 'Vuelo no disponible'), ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                    <h3 class="volara-history-route mb-1">
                                        <?= htmlspecialchars((string) ($reserva['origen'] ?? 'Origen no disponible'), ENT_QUOTES, 'UTF-8') ?>
                                        <span class="text-muted" aria-hidden="true">→</span>
                                        <?= htmlspecialchars((string) ($reserva['destino'] ?? 'Destino no disponible'), ENT_QUOTES, 'UTF-8') ?>
                                    </h3>
                                    <p class="text-muted small mb-0">
                                        Reserva #<?= (int) $reserva['codReserva'] ?>
                                    </p>
                                </div>

                                <span class="badge text-bg-<?= $claseEstado ?> flex-shrink-0">
                                    <?= htmlspecialchars($etiquetaEstado, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>

                            <div class="volara-history-meta">
                                <span>
                                    <i class="bi bi-calendar3" aria-hidden="true"></i>
                                    Vuelo: <?= htmlspecialchars($fechaVuelo, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <span>
                                    <i class="bi bi-clock" aria-hidden="true"></i>
                                    <?= htmlspecialchars($horaVuelo, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <span>
                                    <i class="bi bi-grid-3x3-gap" aria-hidden="true"></i>
                                    Asiento: <?= htmlspecialchars($asiento !== '' ? $asiento : 'Sin asignar', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <span>
                                    <i class="bi bi-layers" aria-hidden="true"></i>
                                    Clase: <?= htmlspecialchars((string) ($reserva['clase'] ?? 'No especificada'), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <span>
                                    <i class="bi bi-calendar-check" aria-hidden="true"></i>
                                    <?= $esCancelada ? 'Cancelada:' : 'Confirmada:' ?>
                                    <?= htmlspecialchars($fechaMovimientoTexto, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>

                            <div class="volara-history-footer">
                                <div class="text-muted small">
                                    Código de reserva: <strong><?= (int) $reserva['codReserva'] ?></strong>
                                </div>
                                <div class="text-end">
                                    <div class="text-muted small">Importe registrado</div>
                                    <div class="volara-history-price">
                                        <?php if ($precio !== null): ?>
                                            $<?= number_format($precio, 2, ',', '.') ?>
                                        <?php else: ?>
                                            No disponible
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>

            <?php if ($totalHistorial > 0): ?>
                <nav class="volara-history-pagination-container" aria-label="Paginación del historial">
                    <div class="volara-history-pagination">
                        <ul class="pagination justify-content-center flex-wrap">
                            <li class="page-item <?= $paginaHistorial <= 1 ? 'disabled' : '' ?>">
                                <?php if ($paginaHistorial > 1): ?>
                                    <a class="page-link" href="<?= htmlspecialchars($urlPagina($paginaHistorial - 1), ENT_QUOTES, 'UTF-8') ?>" aria-label="Página anterior">Anterior</a>
                                <?php else: ?>
                                    <span class="page-link">Anterior</span>
                                <?php endif; ?>
                            </li>

                            <?php for ($numeroPagina = 1; $numeroPagina <= $totalPaginas; $numeroPagina++): ?>
                                <li class="page-item <?= $numeroPagina === $paginaHistorial ? 'active' : '' ?>">
                                    <?php if ($numeroPagina === $paginaHistorial): ?>
                                        <span class="page-link" aria-current="page"><?= $numeroPagina ?></span>
                                    <?php else: ?>
                                        <a class="page-link" href="<?= htmlspecialchars($urlPagina($numeroPagina), ENT_QUOTES, 'UTF-8') ?>"><?= $numeroPagina ?></a>
                                    <?php endif; ?>
                                </li>
                            <?php endfor; ?>

                            <li class="page-item <?= $paginaHistorial >= $totalPaginas ? 'disabled' : '' ?>">
                                <?php if ($paginaHistorial < $totalPaginas): ?>
                                    <a class="page-link" href="<?= htmlspecialchars($urlPagina($paginaHistorial + 1), ENT_QUOTES, 'UTF-8') ?>" aria-label="Página siguiente">Siguiente</a>
                                <?php else: ?>
                                    <span class="page-link">Siguiente</span>
                                <?php endif; ?>
                            </li>
                        </ul>
                    </div>
                    <p class="volara-history-pagination-info">
                        Página <?= $paginaHistorial ?> de <?= $totalPaginas ?>.
                        Mostrando <?= $totalHistorial > 0 ? $offset + 1 : 0 ?> a
                        <?= min($offset + count($historial), $totalHistorial) ?>
                        de <?= $totalHistorial ?> reservas.
                    </p>
                </nav>
            <?php endif; ?>


</div>
    </section>
</main>

<?php require_once __DIR__ . '/../../Includes/footer.php'; ?>
