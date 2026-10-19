<?php
require_once __DIR__ . '/../Includes/functions.php';
requerirRol('administrador');

$conexion = conectarDB();

$aerolineas = $conexion->query(
    "SELECT codAerolinea, nombre FROM aerolineas ORDER BY nombre"
)->fetchAll(PDO::FETCH_ASSOC);

function fechaValida(string $fecha): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $fecha);
    return $d !== false && $d->format('Y-m-d') === $fecha;
}

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
require_once __DIR__ . '/../Includes/header.php';
?>

<main class="container my-4">
    <div class="d-flex justify-content-between align-items-center">
        <h1>Reporte de ventas</h1>
        <button type="button" class="btn btn-outline-secondary d-print-none"
                onclick="window.print()">Imprimir / Guardar PDF</button>
    </div>
    <p class="d-none d-print-block">Emitido el <?= date('d/m/Y H:i') ?></p>

    <form method="get" class="row g-3 align-items-end mb-4 d-print-none">
        <div class="col-md-4">
            <label for="aerolinea" class="form-label">Aerolínea</label>
            <select class="form-select" id="aerolinea" name="aerolinea">
                <option value="0">Todas</option>
                <?php foreach ($aerolineas as $aerolinea): ?>
                    <option value="<?= (int) $aerolinea['codAerolinea'] ?>"
                        <?= $codAerolinea === (int) $aerolinea['codAerolinea'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($aerolinea['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="desde" class="form-label">Desde</label>
            <input type="date" class="form-control" id="desde" name="desde"
                value="<?= htmlspecialchars($desde) ?>">
        </div>
        <div class="col-md-3">
            <label for="hasta" class="form-label">Hasta</label>
            <input type="date" class="form-control" id="hasta" name="hasta"
                value="<?= htmlspecialchars($hasta) ?>">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <a href="reporteVentas.php" class="btn btn-secondary">Limpiar</a>
        </div>
    </form>

    <?php foreach ($errores as $error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endforeach; ?>

    <p>
        <strong>Ventas confirmadas:</strong> <?= (int) $totales['cantidad'] ?> |
        <strong>Monto total:</strong> $<?= number_format((float) $totales['monto'], 2, ',', '.') ?>
    </p>

    <?php if (empty($ventas)): ?>
        <div class="alert alert-info">No hay ventas confirmadas.</div>
    <?php else: ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Reserva</th>
                    <th>Fecha de confirmación</th>
                    <th>Cliente</th>
                    <th>Vuelo</th>
                    <th>Aerolínea</th>
                    <th>Monto</th>
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
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../Includes/footer.php'; ?>