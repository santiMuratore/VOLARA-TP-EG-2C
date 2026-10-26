<?php
    require_once __DIR__ . '/../../Includes/functions.php';
    require_once __DIR__ . '/../../Includes/reportes.php';   // fechaValida()
    requerirRol('ceo aerolínea');
    $conexion = conectarDB();
    $titulo = "Reportes";

    $codAerolinea = $_SESSION['codAerolinea'];
    $porPagina = 10;

    // Arma un enlace conservando los parámetros actuales de la URL (null elimina el parámetro)
    function urlReporte(array $cambios): string
    {
        $params = array_merge($_GET, $cambios);
        foreach ($params as $clave => $valor) {
            if ($valor === null) {
                unset($params[$clave]);
            }
        }
        return '?' . http_build_query($params);
    }

    // Dibuja la barra de páginas de un reporte
    function paginacionReporte(int $actual, int $total, string $parametro): void
    {
        if ($total <= 1) {
            return;
        }
        echo '<nav class="volara-pagination" aria-label="Paginación">';
        if ($actual > 1) {
            echo '<a href="' . htmlspecialchars(urlReporte([$parametro => $actual - 1])) . '" aria-label="Página anterior"><i class="bi bi-chevron-left"></i></a>';
        }
        for ($i = 1; $i <= $total; $i++) {
            if ($i === $actual) {
                echo '<span class="active" aria-current="page">' . $i . '</span>';
            } else {
                echo '<a href="' . htmlspecialchars(urlReporte([$parametro => $i])) . '">' . $i . '</a>';
            }
        }
        if ($actual < $total) {
            echo '<a href="' . htmlspecialchars(urlReporte([$parametro => $actual + 1])) . '" aria-label="Página siguiente"><i class="bi bi-chevron-right"></i></a>';
        }
        echo '</nav>';
    }

    // Tabla del reporte de ventas (se usa en pantalla y en la versión impresa)
    function tablaVentas(array $filas): void
    {
        ?>
        <div class="volara-table-responsive">
            <table class="volara-table">
                <thead>
                    <tr>
                        <th>Reserva</th>
                        <th>Fecha de pago</th>
                        <th>Pasajero</th>
                        <th>Vuelo</th>
                        <th>Monto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filas as $venta): ?>
                        <tr>
                            <td>#<?= (int) $venta['codReserva'] ?></td>
                            <td><?= $venta['fechaPago'] ? date('d/m/Y H:i', strtotime($venta['fechaPago'])) : '-' ?></td>
                            <td><?= htmlspecialchars($venta['nombre'] . ' ' . $venta['apellido']) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($venta['codigoVuelo']) ?></strong><br>
                                <small><?= htmlspecialchars($venta['origen']) ?> → <?= htmlspecialchars($venta['destino']) ?></small>
                            </td>
                            <td>$<?= number_format((float) $venta['precioFinal'], 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    // Tabla del reporte de ocupación (se usa en pantalla y en la versión impresa)
    function tablaOcupacion(array $filas): void
    {
        ?>
        <div class="volara-table-responsive">
            <table class="volara-table">
                <thead>
                    <tr>
                        <th>Vuelo</th>
                        <th>Salida</th>
                        <th>Totales</th>
                        <th>Vendidos</th>
                        <th>Disponibles</th>
                        <th>Ocupación</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filas as $vuelo): ?>
                        <?php
                            $vendidos = (int) $vuelo['asientosTotal'] - (int) $vuelo['asientosDisponibles'];
                            $porcentaje = $vuelo['asientosTotal'] > 0 ? round($vendidos / $vuelo['asientosTotal'] * 100, 1) : 0;
                            $claseBadge = $porcentaje >= 70 ? 'badge-approved' : ($porcentaje >= 30 ? 'badge-pending' : 'badge-neutral');
                        ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($vuelo['codigoVuelo']) ?></strong><br>
                                <small><?= htmlspecialchars($vuelo['origen']) ?> → <?= htmlspecialchars($vuelo['destino']) ?></small>
                            </td>
                            <td>
                                <?= date('d/m/Y', strtotime($vuelo['fecha'])) ?><br>
                                <small><?= substr($vuelo['hora'], 0, 5) ?></small>
                            </td>
                            <td><?= (int) $vuelo['asientosTotal'] ?></td>
                            <td><?= $vendidos ?></td>
                            <td><?= (int) $vuelo['asientosDisponibles'] ?></td>
                            <td><span class="volara-badge <?= $claseBadge ?>"><?= number_format($porcentaje, 1, ',', '.') ?>%</span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    // Nombre de la aerolínea (para el encabezado)
    $consulta = $conexion->prepare("SELECT nombre FROM aerolineas WHERE codAerolinea = :cod");
    $consulta->execute(['cod' => $codAerolinea]);
    $nombreAerolinea = (string) $consulta->fetchColumn();

    // ---------------------------------------------------------------
    // Filtro del reporte de ventas (por fecha de pago)
    // ---------------------------------------------------------------
    $errores = [];
    $desde = trim($_GET['desde'] ?? '');
    $hasta = trim($_GET['hasta'] ?? '');

    if ($desde !== '' && !fechaValida($desde)) {
        $errores['desde'] = 'La fecha "desde" no es válida.';
    }
    if ($hasta !== '' && !fechaValida($hasta)) {
        $errores['hasta'] = 'La fecha "hasta" no es válida.';
    }
    if (empty($errores) && $desde !== '' && $hasta !== '' && $desde > $hasta) {
        $errores['hasta'] = 'La fecha "desde" no puede ser posterior a la fecha "hasta".';
    }

    // ---------------------------------------------------------------
    // Reporte 1: ventas (reservas confirmadas de mis vuelos)
    // Incluye vuelos dados de baja: son ventas reales del pasado.
    // ---------------------------------------------------------------
    $ventasTodas = [];
    $montoVentas = 0;

    if (empty($errores)) {
        $where = ["r.estadoReserva = 'confirmada'", "v.codAerolinea = :cod"];
        $params = ['cod' => $codAerolinea];
        if ($desde !== '') {
            $where[] = 'DATE(r.fechaPago) >= :desde';
            $params['desde'] = $desde;
        }
        if ($hasta !== '') {
            $where[] = 'DATE(r.fechaPago) <= :hasta';
            $params['hasta'] = $hasta;
        }

        $consulta = $conexion->prepare("SELECT r.codReserva, r.fechaPago, r.precioFinal,
                                               u.nombre, u.apellido,
                                               v.codigoVuelo, v.origen, v.destino
                                        FROM reservas r
                                        INNER JOIN vuelos v ON v.codVuelo = r.codVuelo
                                        INNER JOIN usuarios u ON u.codUsuario = r.codUsuario
                                        WHERE " . implode(' AND ', $where) . "
                                        ORDER BY r.fechaPago DESC, r.codReserva DESC");
        $consulta->execute($params);
        $ventasTodas = $consulta->fetchAll(PDO::FETCH_ASSOC);
        $montoVentas = array_sum(array_column($ventasTodas, 'precioFinal'));
    }

    $totalVentas = count($ventasTodas);
    $totalPaginasVentas = max(1, (int) ceil($totalVentas / $porPagina));
    $paginaVentas = filter_input(INPUT_GET, 'pagina_ventas', FILTER_VALIDATE_INT) ?: 1;
    $paginaVentas = min(max($paginaVentas, 1), $totalPaginasVentas);
    $ventasPagina = array_slice($ventasTodas, ($paginaVentas - 1) * $porPagina, $porPagina);

    // ---------------------------------------------------------------
    // Reporte 2: ocupación de mis vuelos activos
    // ---------------------------------------------------------------
    $consulta = $conexion->prepare("SELECT codigoVuelo, origen, destino, fecha, hora, asientosTotal, asientosDisponibles
                                    FROM vuelos
                                    WHERE codAerolinea = :cod AND activo = 1
                                    ORDER BY fecha, hora");
    $consulta->execute(['cod' => $codAerolinea]);
    $ocupacionTodos = $consulta->fetchAll(PDO::FETCH_ASSOC);

    $totalVuelos = count($ocupacionTodos);
    $totalAsientos = array_sum(array_column($ocupacionTodos, 'asientosTotal'));
    $totalDisponibles = array_sum(array_column($ocupacionTodos, 'asientosDisponibles'));
    $ocupacionPromedio = $totalAsientos > 0 ? round(($totalAsientos - $totalDisponibles) / $totalAsientos * 100, 1) : 0;

    $totalPaginasOcupacion = max(1, (int) ceil($totalVuelos / $porPagina));
    $paginaOcupacion = filter_input(INPUT_GET, 'pagina_ocupacion', FILTER_VALIDATE_INT) ?: 1;
    $paginaOcupacion = min(max($paginaOcupacion, 1), $totalPaginasOcupacion);
    $ocupacionPagina = array_slice($ocupacionTodos, ($paginaOcupacion - 1) * $porPagina, $porPagina);

    require_once __DIR__ . '/../../Includes/header.php';
    require_once __DIR__ . '/../../Includes/navbar.php';
?>
<main>
    <div class="container py-4">

        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
            <div>
                <h1 class="h3 mb-0">Reportes de <?= htmlspecialchars($nombreAerolinea) ?></h1>
            </div>
            <button type="button" class="btn-volara-outline btn-volara-sm d-print-none" onclick="window.print()">
                <i class="bi bi-printer"></i> Imprimir
            </button>
        </div>

        <!-- Reporte de ventas -->
        <div class="volara-card mb-4 hoja-impresion">
            <h2 class="h4">Ventas de mi aerolínea</h2>
            <p class="section-subtitle mb-3 d-print-none">Reservas confirmadas, según su fecha de pago.</p>

            <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="get" class="mb-3 d-print-none">
                <div class="row align-items-end">
                    <div class="col-md-4 form-group">
                        <label class="volara-label" for="desde">Desde</label>
                        <input class="volara-input <?= claseInvalida($errores, 'desde') ?>" type="date" id="desde" name="desde"
                               value="<?= htmlspecialchars($desde) ?>">
                        <?php if (isset($errores['desde'])): ?>
                            <div class="form-error"><?= htmlspecialchars($errores['desde']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 form-group">
                        <label class="volara-label" for="hasta">Hasta</label>
                        <input class="volara-input <?= claseInvalida($errores, 'hasta') ?>" type="date" id="hasta" name="hasta"
                               value="<?= htmlspecialchars($hasta) ?>">
                        <?php if (isset($errores['hasta'])): ?>
                            <div class="form-error"><?= htmlspecialchars($errores['hasta']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 form-group d-flex gap-2 d-print-none">
                        <button type="submit" class="btn-volara btn-volara-sm">Filtrar</button>
                        <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn-volara-outline btn-volara-sm">Limpiar</a>
                    </div>
                </div>
            </form>

            <div class="dashboard-grid">
                <div class="stat-card">
                    <div class="stat-icon stat-icon-red"><i class="bi bi-ticket-perforated"></i></div>
                    <div class="stat-value"><?= $totalVentas ?></div>
                    <div class="stat-label">Reservas confirmadas</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon stat-icon-dark"><i class="bi bi-cash-stack"></i></div>
                    <div class="stat-value">$<?= number_format($montoVentas, 2, ',', '.') ?></div>
                    <div class="stat-label">Monto total vendido</div>
                </div>
            </div>

            <?php if (empty($ventasTodas)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="bi bi-receipt"></i></div>
                    <p>No hay ventas confirmadas para el período elegido.</p>
                </div>
            <?php else: ?>
                <!-- En pantalla: página actual. Al imprimir: todos los registros -->
                <div class="d-print-none">
                    <?php tablaVentas($ventasPagina); ?>
                    <?php paginacionReporte($paginaVentas, $totalPaginasVentas, 'pagina_ventas'); ?>
                </div>
                <div class="d-none d-print-block">
                    <?php tablaVentas($ventasTodas); ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Reporte de ocupación -->
        <div class="volara-card">
            <h2 class="h4">Ocupación de mis vuelos</h2>
            <p class="section-subtitle mb-3 d-print-none">Asientos vendidos y disponibles por vuelo (incluye reservas pendientes de pago).</p>

            <div class="dashboard-grid">
                <div class="stat-card">
                    <div class="stat-icon stat-icon-red"><i class="bi bi-airplane"></i></div>
                    <div class="stat-value"><?= $totalVuelos ?></div>
                    <div class="stat-label">Vuelos activos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon stat-icon-dark"><i class="bi bi-pie-chart"></i></div>
                    <div class="stat-value"><?= number_format($ocupacionPromedio, 1, ',', '.') ?>%</div>
                    <div class="stat-label">Ocupación promedio</div>
                </div>
            </div>

            <?php if (empty($ocupacionTodos)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="bi bi-airplane"></i></div>
                    <p>Todavía no cargaste vuelos.</p>
                </div>
            <?php else: ?>
                <div class="d-print-none">
                    <?php tablaOcupacion($ocupacionPagina); ?>
                    <?php paginacionReporte($paginaOcupacion, $totalPaginasOcupacion, 'pagina_ocupacion'); ?>
                </div>
                <div class="d-none d-print-block">
                    <?php tablaOcupacion($ocupacionTodos); ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</main>
<?php
    require_once __DIR__ . '/../../Includes/footer.php';
?>
