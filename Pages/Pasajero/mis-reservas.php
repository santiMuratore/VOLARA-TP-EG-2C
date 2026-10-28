<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../Includes/functions.php';
require_once __DIR__ . '/../../Includes/functionsF2.php';

$baseUrl = rtrim(BASE_URL, '/');
$urlMisReservas = BASE_URL . '/Pages/Pasajero/mis-reservas.php';

if (empty($_SESSION['codUsuario'])) {
    header('Location: ' . BASE_URL . '/Auth/login.php');
    exit;
}

$rolUsuario = trim((string) (
    $_SESSION['tipoUsuario'] ?? $_SESSION['rol'] ?? ''
));
$rolUsuario = function_exists('mb_strtolower')
    ? mb_strtolower($rolUsuario, 'UTF-8')
    : strtolower($rolUsuario);
$rolUsuario = strtr($rolUsuario, [
    'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
]);

if (!in_array($rolUsuario, ['usuario', 'pasajero', 'cliente'], true)) {
    header('Location: ' . BASE_URL . '/Pages/Publico/index.php');
    exit;
}

$titulo = 'Mis reservas';
$pageDescription = 'Consultá tus reservas de vuelos en VOLARA.';
$codUsuario = (int) $_SESSION['codUsuario'];
$porPagina = 4;
$paginaSolicitada = max(1, (int) ($_GET['pagina'] ?? 1));

$reservas = [];
$gruposPendientes = [];
$error = '';
$mensajeExito = '';
$totalReservas = 0;
$totalPaginas = 1;
$pagina = 1;
$offset = 0;
$conteos = [
    'todas' => 0,
    'pendiente' => 0,
    'confirmada' => 0,
    'cancelada' => 0,
];

$filtros = [
    'todas' => [
        'titulo' => 'Todas',
        'condicion' => '1 = 1',
    ],
    'pendiente' => [
        'titulo' => 'Pendientes',
        'condicion' => "r.estadoReserva = 'pendiente de pago'",
    ],
    'confirmada' => [
        'titulo' => 'Confirmadas',
        'condicion' => "r.estadoReserva = 'confirmada'",
    ],
    'cancelada' => [
        'titulo' => 'Canceladas',
        'condicion' => "r.estadoReserva = 'cancelada'",
    ],
];

$estadoFiltro = strtolower(trim((string) ($_GET['estado'] ?? 'todas')));
if (!isset($filtros[$estadoFiltro])) {
    $estadoFiltro = 'todas';
}
$condicionFiltro = $filtros[$estadoFiltro]['condicion'];

$urlPagina = static function (int $numeroPagina, string $estado) use ($urlMisReservas): string {
    return $urlMisReservas . '?' . http_build_query([
        'estado' => $estado,
        'pagina' => max(1, $numeroPagina),
    ]);
};

if (empty($_SESSION['csrf_reservas'])) {
    $_SESSION['csrf_reservas'] = bin2hex(random_bytes(32));
}
$csrfToken = (string) $_SESSION['csrf_reservas'];

$resultado = (string) ($_GET['resultado'] ?? '');
if ($resultado === 'confirmada') {
    $mensajeExito = 'La reserva se confirmó correctamente.';
    if (!empty($_SESSION['flash_pago']) && is_array($_SESSION['flash_pago'])) {
        $cantidadPagada = (int) ($_SESSION['flash_pago']['cantidad'] ?? 0);
        $totalPagado = (float) ($_SESSION['flash_pago']['total'] ?? 0);
        $mensajeExito = 'Pago confirmado: ' . $cantidadPagada . ' '
            . ($cantidadPagada === 1 ? 'asiento' : 'asientos')
            . ' por un total de $' . number_format($totalPagado, 2, ',', '.') . '.';
    }
    unset($_SESSION['flash_pago']);
} elseif ($resultado === 'cancelada') {
    $mensajeExito = 'La reserva se canceló correctamente.';
}
if (($_GET['creada'] ?? '') === '1') {
    $mensajeExito = 'Tu reserva se creó correctamente y quedó pendiente de pago.';
    if ((int) ($_GET['cantidad'] ?? 1) > 1) {
        $mensajeExito = 'Tus asientos quedaron reservados. Se pagan juntos, con una sola confirmación, desde «Pagos pendientes».';
    }
}

try {
    $conexion = conectarDB();
    if (!$conexion instanceof PDO) {
        throw new RuntimeException('La conexión no devolvió un objeto PDO.');
    }
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    /* Confirmar o cancelar una reserva. */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $accion = trim((string) ($_POST['accion'] ?? ''));
        $codReservaPost = filter_var(
            $_POST['codReserva'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $csrfRecibido = (string) ($_POST['csrf_token'] ?? '');

        if (!hash_equals($csrfToken, $csrfRecibido)) {
            $error = 'El formulario venció. Recargá la página e intentá nuevamente.';
        } elseif ($codReservaPost === false || $codReservaPost === null) {
            $error = 'La reserva seleccionada no es válida.';
        } elseif (!in_array($accion, ['confirmar', 'cancelar'], true)) {
            $error = 'La acción solicitada no es válida.';
        } else {
            try {
                $conexion->beginTransaction();

                $sqlActual = "
                    SELECT r.codReserva, r.codVuelo, r.estadoReserva, r.codCompra, v.fecha, v.hora
                    FROM reservas r
                    INNER JOIN vuelos v ON v.codVuelo = r.codVuelo
                    WHERE r.codReserva = :codReserva
                      AND r.codUsuario = :codUsuario
                    FOR UPDATE
                ";
                $stmt = $conexion->prepare($sqlActual);
                $stmt->execute([
                    'codReserva' => (int) $codReservaPost,
                    'codUsuario' => $codUsuario,
                ]);
                $reservaActual = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$reservaActual) {
                    throw new DomainException('La reserva no existe o no pertenece a tu cuenta.');
                }

                $estadoActual = strtolower(trim((string) $reservaActual['estadoReserva']));

                if ($accion === 'confirmar') {
                    if ($estadoActual !== 'pendiente de pago') {
                        throw new DomainException('Esta reserva ya no está pendiente de pago.');
                    }

                    /*
                     * El pago es único por compra: se confirman juntas todas las
                     * reservas pendientes que comparten el mismo codCompra.
                     * Las reservas anteriores sin codCompra se pagan por separado.
                     */
                    $codCompraActual = (string) ($reservaActual['codCompra'] ?? '');

                    if ($codCompraActual !== '') {
                        $stmt = $conexion->prepare("
                            SELECT codReserva, precioFinal
                            FROM reservas
                            WHERE codUsuario = :codUsuario
                              AND codCompra = :codCompra
                              AND estadoReserva = 'pendiente de pago'
                            FOR UPDATE
                        ");
                        $stmt->execute([
                            'codUsuario' => $codUsuario,
                            'codCompra' => $codCompraActual,
                        ]);
                    } else {
                        $stmt = $conexion->prepare("
                            SELECT codReserva, precioFinal
                            FROM reservas
                            WHERE codUsuario = :codUsuario
                              AND codReserva = :codReserva
                              AND estadoReserva = 'pendiente de pago'
                            FOR UPDATE
                        ");
                        $stmt->execute([
                            'codUsuario' => $codUsuario,
                            'codReserva' => (int) $codReservaPost,
                        ]);
                    }

                    $reservasAPagar = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    if (empty($reservasAPagar)) {
                        throw new DomainException('No hay reservas pendientes de pago para confirmar. Actualizá la página.');
                    }

                    $codigosAPagar = array_map('intval', array_column($reservasAPagar, 'codReserva'));
                    $totalAPagar = round((float) array_sum(array_column($reservasAPagar, 'precioFinal')), 2);
                    $marcadores = implode(',', array_fill(0, count($codigosAPagar), '?'));

                    $stmt = $conexion->prepare("
                        UPDATE reservas
                        SET estadoReserva = 'confirmada', fechaPago = NOW()
                        WHERE codUsuario = ?
                          AND estadoReserva = 'pendiente de pago'
                          AND codReserva IN ($marcadores)
                    ");
                    $stmt->execute(array_merge([$codUsuario], $codigosAPagar));

                    if ($stmt->rowCount() !== count($codigosAPagar)) {
                        throw new DomainException('No se pudo confirmar el pago. Actualizá la página e intentá nuevamente.');
                    }

                    $conexion->commit();
                    $_SESSION['csrf_reservas'] = bin2hex(random_bytes(32));
                    $_SESSION['flash_pago'] = [
                        'cantidad' => count($codigosAPagar),
                        'total' => $totalAPagar,
                    ];
                    header('Location: ' . $urlMisReservas . '?' . http_build_query([
                        'resultado' => 'confirmada',
                        'estado' => 'confirmada',
                        'pagina' => 1,
                    ]));
                    exit;
                }

                if ($accion === 'cancelar') {
                    if (!in_array($estadoActual, ['pendiente de pago', 'confirmada'], true)) {
                        throw new DomainException('Esta reserva ya no puede cancelarse.');
                    }

                    $fechaHoraSalida = (string) $reservaActual['fecha'] . ' ' . (string) $reservaActual['hora'];
                    $timestampSalida = strtotime($fechaHoraSalida);
                    if ($timestampSalida === false) {
                        throw new DomainException('No se pudo determinar la fecha de salida del vuelo.');
                    }
                    if (($timestampSalida - time()) < (72 * 60 * 60)) {
                        throw new DomainException('La reserva solo puede cancelarse hasta 72 horas antes de la salida.');
                    }

                    $stmt = $conexion->prepare(" 
                        UPDATE reservas
                        SET estadoReserva = 'cancelada', fechaCancelacion = NOW()
                        WHERE codReserva = :codReserva
                          AND codUsuario = :codUsuario
                          AND estadoReserva IN ('pendiente de pago', 'confirmada')
                    ");
                    $stmt->execute([
                        'codReserva' => (int) $codReservaPost,
                        'codUsuario' => $codUsuario,
                    ]);
                    if ($stmt->rowCount() !== 1) {
                        throw new DomainException('No se pudo cancelar la reserva. Actualizá la página e intentá nuevamente.');
                    }

                    /* Liberar el asiento asociado a esta reserva. */
                    $stmt = $conexion->prepare(" 
                        UPDATE vuelos
                        SET asientosDisponibles = asientosDisponibles + 1
                        WHERE codVuelo = :codVuelo
                          AND asientosDisponibles < asientosTotal
                    ");
                    $stmt->execute(['codVuelo' => (int) $reservaActual['codVuelo']]);
                    if ($stmt->rowCount() !== 1) {
                        throw new DomainException('No se pudo devolver el asiento al vuelo. No se guardó la cancelación.');
                    }

                    $conexion->commit();
                    $_SESSION['csrf_reservas'] = bin2hex(random_bytes(32));
                    header('Location: ' . $urlMisReservas . '?' . http_build_query([
                        'resultado' => 'cancelada',
                        'estado' => 'cancelada',
                        'pagina' => 1,
                    ]));
                    exit;
                }
            } catch (DomainException $e) {
                if ($conexion->inTransaction()) {
                    $conexion->rollBack();
                }
                $error = $e->getMessage();
            } catch (Throwable $e) {
                if ($conexion->inTransaction()) {
                    $conexion->rollBack();
                }
                error_log('VOLARA - Error al procesar una reserva: ' . $e->getMessage());
                $error = 'No se pudo procesar la acción. Intentá nuevamente.';
            }
        }
    }

    $conteos = [
        'todas' => 0,
        'pendiente' => 0,
        'confirmada' => 0,
        'cancelada' => 0,
    ];

    $stmt = $conexion->prepare(" 
        SELECT estadoReserva, COUNT(*) AS cantidad
        FROM reservas
        WHERE codUsuario = :codUsuario
        GROUP BY estadoReserva
    ");
    $stmt->execute(['codUsuario' => $codUsuario]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        $estadoContado = (string) $fila['estadoReserva'];
        $cantidad = (int) $fila['cantidad'];
        $conteos['todas'] += $cantidad;
        if ($estadoContado === 'pendiente de pago') {
            $conteos['pendiente'] += $cantidad;
        } elseif ($estadoContado === 'confirmada') {
            $conteos['confirmada'] += $cantidad;
        } elseif ($estadoContado === 'cancelada') {
            $conteos['cancelada'] += $cantidad;
        }
    }

    $sqlTotal = "
        SELECT COUNT(*)
        FROM reservas r
        WHERE r.codUsuario = :codUsuario
          AND {$condicionFiltro}
    ";
    $stmt = $conexion->prepare($sqlTotal);
    $stmt->execute(['codUsuario' => $codUsuario]);
    $totalReservas = (int) $stmt->fetchColumn();

    $datosPaginacion = paginar($totalReservas, $porPagina, $paginaSolicitada);
    $pagina = (int) $datosPaginacion['paginaActual'];
    $totalPaginas = (int) $datosPaginacion['totalPaginas'];
    $offset = (int) $datosPaginacion['offset'];

    $sqlReservas = "
        SELECT
            r.codReserva,
            r.estadoReserva,
            r.precioFinal,
            r.asiento,
            r.codCompra,
            v.codigoVuelo,
            v.origen,
            v.destino,
            v.fecha,
            v.hora,
            v.clase,
            a.nombre AS nombreAerolinea
        FROM reservas r
        LEFT JOIN vuelos v ON v.codVuelo = r.codVuelo
        LEFT JOIN aerolineas a ON a.codAerolinea = v.codAerolinea
        WHERE r.codUsuario = :codUsuario
          AND {$condicionFiltro}
        ORDER BY v.fecha DESC, v.hora DESC, r.codReserva DESC
        LIMIT :limite OFFSET :offset
    ";
    $stmt = $conexion->prepare($sqlReservas);
    $stmt->bindValue(':codUsuario', $codUsuario, PDO::PARAM_INT);
    $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conexion->prepare("
        SELECT r.codReserva, r.codCompra, r.asiento, r.precioFinal,
               v.codigoVuelo, v.origen, v.destino, v.fecha, v.hora
        FROM reservas r
        LEFT JOIN vuelos v ON v.codVuelo = r.codVuelo
        WHERE r.codUsuario = :codUsuario
          AND r.estadoReserva = 'pendiente de pago'
        ORDER BY v.fecha, v.hora, r.codReserva
    ");
    $stmt->execute(['codUsuario' => $codUsuario]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        $clave = ((string) ($fila['codCompra'] ?? '')) !== ''
            ? 'c' . $fila['codCompra']
            : 'r' . $fila['codReserva'];

        if (!isset($gruposPendientes[$clave])) {
            $gruposPendientes[$clave] = [
                'codReserva' => (int) $fila['codReserva'],
                'codigoVuelo' => (string) ($fila['codigoVuelo'] ?? ''),
                'origen' => (string) ($fila['origen'] ?? ''),
                'destino' => (string) ($fila['destino'] ?? ''),
                'fecha' => (string) ($fila['fecha'] ?? ''),
                'hora' => (string) ($fila['hora'] ?? ''),
                'asientos' => [],
                'cantidad' => 0,
                'total' => 0.0,
            ];
        }

        $asientoGrupo = trim((string) ($fila['asiento'] ?? ''));
        if ($asientoGrupo !== '') {
            $gruposPendientes[$clave]['asientos'][] = $asientoGrupo;
        }
        $gruposPendientes[$clave]['cantidad']++;
        $gruposPendientes[$clave]['total'] += (float) $fila['precioFinal'];
    }
} catch (Throwable $e) {
    error_log('VOLARA - Error cargando reservas: ' . $e->getMessage());
    $error = 'No se pudieron cargar tus reservas. Revisá la conexión con la base de datos o el registro de errores de PHP.';
}

require __DIR__ . '/../../Includes/header.php';
require __DIR__ . '/../../Includes/navbar.php';

?>

<style>
    .volara-reservas-filtros .btn { border-radius: 999px; }
    .volara-pagination-container { display: flex; justify-content: center; width: 100%; margin: 1.75rem 0 .5rem; }
    .volara-pagination { max-width: 100%; overflow-x: auto; }
    .volara-pagination .pagination { display: flex; flex-wrap: wrap; justify-content: center; align-items: center; gap: .35rem; margin: 0; padding: 0; }
    .volara-pagination .page-link { display: flex; align-items: center; justify-content: center; min-width: 36px; min-height: 36px; padding: .4rem .6rem; border: 1px solid #DDE2E7; border-radius: 8px !important; background-color: #fff; color: #374151; font-size: .875rem; font-weight: 600; line-height: 1.2; }
    .volara-pagination .page-link:hover { border-color: #E51B23; background-color: #FDEAEB; color: #C9141C; }
    .volara-pagination .page-item.active .page-link { border-color: #E51B23; background-color: #E51B23; color: #fff; }
    .volara-pagination .page-link:focus-visible { outline: 3px solid rgba(229,27,35,.25); outline-offset: 2px; }
    .volara-pagination .page-item.disabled .page-link { background-color: #F4F6F8; color: #9CA3AF; cursor: not-allowed; }
    .volara-pagination-info { margin-top: .8rem; text-align: center; color: #6B7280; font-size: .875rem; }
    @media (max-width: 576px) { .volara-pagination .pagination { gap: .25rem; } .volara-pagination .page-link { min-width: 31px; min-height: 31px; padding: .3rem .45rem; font-size: .8rem; } }
</style>

<main id="contenido-principal" class="volara-public-page volara-pasajero-page" tabindex="-1">
    <section class="page-header">
        <div class="container">
            <h1>Mis reservas</h1>
            <p class="text-muted mb-3">Consultá los detalles de tus vuelos, el asiento y el estado de tus reservas.</p>
            <nav aria-label="Ruta de navegación">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= htmlspecialchars(BASE_URL . '/Pages/Publico/index.php', ENT_QUOTES, 'UTF-8') ?>">Inicio</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Mis reservas</li>
                </ol>
            </nav>
        </div>
    </section>

    <section class="volara-public-section">
        <div class="container">
            <?php if ($mensajeExito !== ''): ?>
                <div class="volara-alert alert-success" role="status">
                    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                    <span><?= htmlspecialchars($mensajeExito, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                <div>
                    <h2 class="h4 mb-1">Tus viajes</h2>
                    <p class="text-muted mb-0">
                        <?= $totalReservas ?> <?= $totalReservas === 1 ? 'reserva encontrada' : 'reservas encontradas' ?>
                        <?php if ($estadoFiltro !== 'todas'): ?>
                            en «<?= htmlspecialchars($filtros[$estadoFiltro]['titulo'], ENT_QUOTES, 'UTF-8') ?>»
                        <?php endif; ?>
                    </p>
                </div>
                <a href="<?= htmlspecialchars(BASE_URL . '/Pages/Publico/vuelos.php', ENT_QUOTES, 'UTF-8') ?>" class="btn btn-volara">
                    <i class="bi bi-search me-2" aria-hidden="true"></i>Buscar vuelos
                </a>
            </div>

            <nav class="volara-reservas-filtros mb-4" aria-label="Filtrar reservas">
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($filtros as $claveFiltro => $datosFiltro): ?>
                        <?php $filtroActivo = ($estadoFiltro === $claveFiltro); ?>
                        <a href="<?= htmlspecialchars($urlPagina(1, $claveFiltro), ENT_QUOTES, 'UTF-8') ?>"
                           class="btn <?= $filtroActivo ? 'btn-volara' : 'btn-volara-outline' ?>"
                           <?= $filtroActivo ? 'aria-current="page"' : '' ?>>
                            <?= htmlspecialchars($datosFiltro['titulo'], ENT_QUOTES, 'UTF-8') ?>
                            <span class="ms-1">(<?= (int) $conteos[$claveFiltro] ?>)</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </nav>

            <?php if (!empty($gruposPendientes) && in_array($estadoFiltro, ['todas', 'pendiente'], true)): ?>
                <section class="mb-5" aria-labelledby="titulo-pagos-pendientes">
                    <h2 class="h4 mb-1" id="titulo-pagos-pendientes">Pagos pendientes</h2>
                    <p class="text-muted mb-3">Los asientos reservados juntos se pagan con una sola confirmación.</p>

                    <div class="row g-4">
                        <?php foreach ($gruposPendientes as $grupo): ?>
                            <?php
                            $fechaGrupo = $grupo['fecha'] !== '' && strtotime($grupo['fecha']) !== false
                                ? date('d/m/Y', strtotime($grupo['fecha']))
                                : 'Fecha no disponible';
                            $horaGrupo = $grupo['hora'] !== '' ? substr($grupo['hora'], 0, 5) : '';
                            ?>
                            <div class="col-12">
                                <article class="volara-card">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                                        <div class="min-w-0">
                                            <h3 class="h5 mb-1">
                                                <?= htmlspecialchars($grupo['origen'], ENT_QUOTES, 'UTF-8') ?>
                                                <i class="bi bi-arrow-right text-danger mx-1" aria-hidden="true"></i>
                                                <?= htmlspecialchars($grupo['destino'], ENT_QUOTES, 'UTF-8') ?>
                                            </h3>
                                            <p class="text-muted small mb-2">
                                                Vuelo <?= htmlspecialchars($grupo['codigoVuelo'], ENT_QUOTES, 'UTF-8') ?>
                                                · <?= htmlspecialchars($fechaGrupo, ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($horaGrupo, ENT_QUOTES, 'UTF-8') ?>
                                            </p>
                                            <p class="mb-0">
                                                <strong><?= (int) $grupo['cantidad'] ?> <?= (int) $grupo['cantidad'] === 1 ? 'asiento' : 'asientos' ?>:</strong>
                                                <?php foreach ($grupo['asientos'] as $asientoGrupo): ?>
                                                    <span class="badge text-bg-light border"><?= htmlspecialchars($asientoGrupo, ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php endforeach; ?>
                                            </p>
                                        </div>
                                        <div class="text-md-end">
                                            <p class="text-muted small mb-0">Total a pagar</p>
                                            <p class="h4 mb-3">$<?= number_format($grupo['total'], 2, ',', '.') ?></p>
                                            <form method="post" action="<?= htmlspecialchars($urlMisReservas . '?' . http_build_query(['estado' => $estadoFiltro, 'pagina' => $pagina]), ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="codReserva" value="<?= (int) $grupo['codReserva'] ?>">
                                                <input type="hidden" name="accion" value="confirmar">
                                                <button type="submit" class="btn btn-volara">
                                                    <i class="bi bi-credit-card me-1" aria-hidden="true"></i>Confirmar pago de $<?= number_format($grupo['total'], 2, ',', '.') ?>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </article>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($error === '' && empty($reservas)): ?>
                <div class="volara-card text-center py-5">
                    <div class="stat-icon stat-icon-red mx-auto mb-3"><i class="bi bi-ticket-perforated" aria-hidden="true"></i></div>
                    <?php if ($estadoFiltro === 'todas'): ?>
                        <h2 class="h4">Todavía no tenés reservas</h2>
                        <p class="text-muted mb-4">Cuando reserves un vuelo, vas a poder consultar sus detalles desde esta sección.</p>
                        <a href="<?= htmlspecialchars(BASE_URL . '/Pages/Publico/vuelos.php', ENT_QUOTES, 'UTF-8') ?>" class="btn btn-volara">Explorar vuelos</a>
                    <?php else: ?>
                        <h2 class="h4">No hay reservas en esta categoría</h2>
                        <p class="text-muted mb-4">No tenés reservas <?= htmlspecialchars(strtolower($filtros[$estadoFiltro]['titulo']), ENT_QUOTES, 'UTF-8') ?>.</p>
                        <a href="<?= htmlspecialchars($urlPagina(1, 'todas'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-volara">Ver todas las reservas</a>
                    <?php endif; ?>
                </div>
            <?php elseif (!empty($reservas)): ?>
                <div class="row g-4">
                    <?php foreach ($reservas as $reserva): ?>
                        <?php
                        $estado = trim((string) ($reserva['estadoReserva'] ?? 'Sin estado'));
                        $estadoNormalizado = strtolower($estado);
                        $claseEstado = match ($estadoNormalizado) {
                            'confirmada' => 'success',
                            'cancelada' => 'danger',
                            'pendiente de pago' => 'warning',
                            default => 'secondary',
                        };

                        $fechaVuelo = 'Fecha no disponible';
                        if (!empty($reserva['fecha'])) {
                            $timestampFecha = strtotime((string) $reserva['fecha']);
                            if ($timestampFecha !== false) {
                                $fechaVuelo = date('d/m/Y', $timestampFecha);
                            }
                        }
                        $horaVuelo = !empty($reserva['hora']) ? substr((string) $reserva['hora'], 0, 5) : 'Hora no disponible';
                        $precio = is_numeric($reserva['precioFinal'] ?? null) ? (float) $reserva['precioFinal'] : null;
                        $asiento = trim((string) ($reserva['asiento'] ?? ''));

                        $puedeCancelar = false;
                        if (in_array($estadoNormalizado, ['pendiente de pago', 'confirmada'], true) && !empty($reserva['fecha']) && !empty($reserva['hora'])) {
                            $timestampSalida = strtotime((string) $reserva['fecha'] . ' ' . (string) $reserva['hora']);
                            if ($timestampSalida !== false) {
                                $puedeCancelar = ($timestampSalida - time()) >= (72 * 60 * 60);
                            }
                        }
                        ?>
                        <div class="col-12 col-lg-6">
                            <article class="volara-card reservation-summary h-100">
                                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                    <div class="min-w-0">
                                        <p class="text-muted small mb-1">Reserva #<?= (int) $reserva['codReserva'] ?></p>
                                        <h3 class="h5 mb-2">
                                            <?= htmlspecialchars((string) ($reserva['origen'] ?? 'Origen no disponible'), ENT_QUOTES, 'UTF-8') ?>
                                            <i class="bi bi-arrow-right text-danger mx-1" aria-hidden="true"></i>
                                            <?= htmlspecialchars((string) ($reserva['destino'] ?? 'Destino no disponible'), ENT_QUOTES, 'UTF-8') ?>
                                        </h3>
                                        <p class="text-muted small mb-0"><?= htmlspecialchars((string) ($reserva['nombreAerolinea'] ?? 'Aerolínea no disponible'), ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                    <span class="badge text-bg-<?= $claseEstado ?> flex-shrink-0"><?= htmlspecialchars($estado, ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <hr>
                                <div class="reservation-summary-details">
                                    <p><i class="bi bi-airplane me-2" aria-hidden="true"></i><strong>Vuelo:</strong> <?= htmlspecialchars((string) ($reserva['codigoVuelo'] ?? 'No disponible'), ENT_QUOTES, 'UTF-8') ?></p>
                                    <p><i class="bi bi-calendar3 me-2" aria-hidden="true"></i><strong>Fecha:</strong> <?= htmlspecialchars($fechaVuelo, ENT_QUOTES, 'UTF-8') ?></p>
                                    <p><i class="bi bi-clock me-2" aria-hidden="true"></i><strong>Hora:</strong> <?= htmlspecialchars($horaVuelo, ENT_QUOTES, 'UTF-8') ?></p>
                                    <p><i class="bi bi-grid me-2" aria-hidden="true"></i><strong>Clase:</strong> <?= htmlspecialchars((string) ($reserva['clase'] ?? 'No especificada'), ENT_QUOTES, 'UTF-8') ?></p>
                                    <p><i class="bi bi-person-workspace me-2" aria-hidden="true"></i><strong>Asiento:</strong>
                                        <?php if ($asiento !== ''): ?>
                                            <span class="badge text-bg-light border"><?= htmlspecialchars($asiento, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">No asignado</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="reservation-summary-price">
                                    <span class="text-muted">Precio de la reserva</span>
                                    <strong><?php if ($precio !== null): ?>$<?= number_format($precio, 2, ',', '.') ?><?php else: ?>No disponible<?php endif; ?></strong>
                                </div>
                                <div class="d-flex flex-wrap gap-2 mt-4">
                                    <?php if ($estadoNormalizado === 'pendiente de pago'): ?>
                                        <p class="text-muted small mb-0 align-self-center">
                                            <i class="bi bi-credit-card me-1" aria-hidden="true"></i>Se paga junto con el resto de tu compra, desde «Pagos pendientes».
                                        </p>
                                    <?php endif; ?>
                                    <?php if ($puedeCancelar): ?>
                                        <form method="post" action="<?= htmlspecialchars($urlMisReservas . '?' . http_build_query(['estado' => $estadoFiltro, 'pagina' => $pagina]), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('¿Seguro que querés cancelar esta reserva?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="codReserva" value="<?= (int) $reserva['codReserva'] ?>">
                                            <input type="hidden" name="accion" value="cancelar">
                                            <button type="submit" class="btn btn-volara-outline"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>Cancelar reserva</button>
                                        </form>
                                    <?php elseif (in_array($estadoNormalizado, ['pendiente de pago', 'confirmada'], true)): ?>
                                        <p class="text-muted small mb-0 align-self-center"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>Ya no puede cancelarse porque faltan menos de 72 horas para la salida.</p>
                                    <?php endif; ?>
                                    <?php if ($estadoNormalizado === 'cancelada'): ?>
                                        <span class="text-muted small"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>Esta reserva fue cancelada.</span>
                                    <?php endif; ?>
                                </div>
                            </article>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalReservas > 0): ?>
                    <nav class="volara-pagination-container" aria-label="Páginas de reservas">
                        <div class="volara-pagination">
                            <ul class="pagination">
                               
                                <?php for ($n = 1; $n <= $totalPaginas; $n++): ?>
                                    <li class="page-item <?= $n === $pagina ? 'active' : '' ?>">
                                        <?php if ($n === $pagina): ?>
                                            <span class="page-link" aria-current="page"><?= $n ?></span>
                                        <?php else: ?>
                                            <a class="page-link" href="<?= htmlspecialchars($urlPagina($n, $estadoFiltro), ENT_QUOTES, 'UTF-8') ?>"><?= $n ?></a>
                                        <?php endif; ?>
                                    </li>
                                <?php endfor; ?>
    
                            </ul>
                            
                        </div>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php
require __DIR__ . '/../../Includes/footer.php';
?>
