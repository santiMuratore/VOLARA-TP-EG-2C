<?php
require_once __DIR__ . '/../../../Includes/functions.php';
require_once __DIR__ . '/../../../Includes/reportes.php';
requerirRol('administrador');

$conexion = conectarDB();

$aerolineas = $conexion->query(
    "SELECT codAerolinea, nombre FROM aerolineas ORDER BY nombre"
)->fetchAll(PDO::FETCH_ASSOC);


$errores = [];
$codAerolinea = (int) ($_GET['aerolinea'] ?? 0);
$desde = trim($_GET['desde'] ?? '');
$hasta = trim($_GET['hasta'] ?? '');

if ($desde !== '' && !fechaValida($desde)) {
    $errores[] = 'La fecha "desde" no es válida.';
}
if ($hasta !== '' && !fechaValida($hasta)) {
    $errores[] = 'La fecha "hasta" no es válida.';
}
if (empty($errores) && $desde !== '' && $hasta !== '' && $desde > $hasta) {
    $errores[] = 'La fecha "desde" no puede ser posterior a la fecha "hasta".';
}

$totales = ['cantidad' => 0, 'monto' => 0];
$ventas = [];

if (empty($errores)) {
    $where = ["r.estadoReserva = 'confirmada'"];
    $params = [];

    if ($codAerolinea > 0) {
        $where[] = 'v.codAerolinea = :codAerolinea';
        $params['codAerolinea'] = $codAerolinea;
    }
    if ($desde !== '') {
        $where[] = 'DATE(r.fechaPago) >= :desde';
        $params['desde'] = $desde;
    }
    if ($hasta !== '') {
        $where[] = 'DATE(r.fechaPago) <= :hasta';
        $params['hasta'] = $hasta;
    }
    $condiciones = implode(' AND ', $where);

    $consulta = $conexion->prepare(
        "SELECT COUNT(*) AS cantidad, COALESCE(SUM(r.precioFinal), 0) AS monto
         FROM reservas r
         INNER JOIN vuelos v ON v.codVuelo = r.codVuelo
         WHERE $condiciones"
    );
    $consulta->execute($params);
    $totales = $consulta->fetch(PDO::FETCH_ASSOC);

    $consulta = $conexion->prepare(
        "SELECT r.codReserva, r.fechaPago, r.precioFinal,
                u.nombre, u.apellido,
                v.codigoVuelo, v.origen, v.destino,
                a.nombre AS aerolinea
         FROM reservas r
         INNER JOIN usuarios u ON u.codUsuario = r.codUsuario
         INNER JOIN vuelos v ON v.codVuelo = r.codVuelo
         INNER JOIN aerolineas a ON a.codAerolinea = v.codAerolinea
         WHERE $condiciones
         ORDER BY r.fechaPago DESC"
    );
    $consulta->execute($params);
    $ventas = $consulta->fetchAll(PDO::FETCH_ASSOC);
}

$titulo = 'Reporte de ventas';
require_once __DIR__ . '/../../../Includes/header.php';
require_once __DIR__ . '/../../../Includes/navbar.php';
?>

<main class="container my-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h1 class="mb-0">Reporte de ventas</h1>
        <button type="button" class="btn-volara-outline d-print-none" onclick="window.print()">
            <i class="bi bi-printer" aria-hidden="true"></i> Imprimir / Guardar PDF
        </button>
    </div>
    <p class="d-none d-print-block">Emitido el <?= date('d/m/Y H:i') ?></p>

    <form method="get" class="search-box mb-4 d-print-none">
        <div class="search-fields">
            <div class="form-group">
                <label for="aerolinea" class="volara-label">Aerolínea</label>
                <select class="volara-select" id="aerolinea" name="aerolinea">
                    <option value="0">Todas</option>
                    <?php foreach ($aerolineas as $aerolinea): ?>
                        <option value="<?= (int) $aerolinea['codAerolinea'] ?>"
                            <?= $codAerolinea === (int) $aerolinea['codAerolinea'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($aerolinea['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="desde" class="volara-label">Desde</label>
                <input type="date" class="volara-input" id="desde" name="desde"
                       value="<?= htmlspecialchars($desde) ?>">
            </div>
            <div class="form-group">
                <label for="hasta" class="volara-label">Hasta</label>
                <input type="date" class="volara-input" id="hasta" name="hasta"
                       value="<?= htmlspecialchars($hasta) ?>">
            </div>
            <div class="search-btn-wrap gap-2">
                <button type="submit" class="btn-volara">Filtrar</button>
                <a href="reporteVentas.php" class="btn-volara-outline">Limpiar</a>
            </div>
        </div>
    </form>

    <?php foreach ($errores as $error): ?>
        <div class="volara-alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endforeach; ?>

    <div class="dashboard-grid">
        <div class="stat-card">
            <div class="stat-icon stat-icon-red"><i class="bi bi-receipt" aria-hidden="true"></i></div>
            <div class="stat-value"><?= (int) $totales['cantidad'] ?></div>
            <div class="stat-label">Ventas confirmadas</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-dark"><i class="bi bi-cash-coin" aria-hidden="true"></i></div>
            <div class="stat-value">$<?= number_format((float) $totales['monto'], 2, ',', '.') ?></div>
            <div class="stat-label">Monto total</div>
        </div>
    </div>

    <?php if (empty($ventas)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="bi bi-receipt" aria-hidden="true"></i></div>
            <p>No hay ventas confirmadas.</p>
        </div>
    <?php else: ?>
        <div class="volara-table-responsive">
            <table class="volara-table">
                <thead>
                    <tr>
                        <th scope="col">Reserva</th>
                        <th scope="col">Fecha de confirmación</th>
                        <th scope="col">Cliente</th>
                        <th scope="col">Vuelo</th>
                        <th scope="col">Aerolínea</th>
                        <th scope="col">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ventas as $venta): ?>
                        <tr>
                            <td><?= (int) $venta['codReserva'] ?></td>
                            <td><?= htmlspecialchars($venta['fechaPago']) ?></td>
                            <td><?= htmlspecialchars($venta['nombre'] . ' ' . $venta['apellido']) ?></td>
                            <td><?= htmlspecialchars($venta['codigoVuelo'] . ' (' . $venta['origen'] . ' → ' . $venta['destino'] . ')') ?></td>
                            <td><?= htmlspecialchars($venta['aerolinea']) ?></td>
                            <td>$<?= number_format((float) $venta['precioFinal'], 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../../../Includes/footer.php'; ?>