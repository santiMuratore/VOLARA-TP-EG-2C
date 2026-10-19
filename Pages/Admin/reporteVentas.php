<?php
require_once __DIR__ . '/../Includes/functions.php';
requerirRol('administrador');

$conexion = conectarDB();

$totales = $conexion->query(
    "SELECT COUNT(*) AS cantidad, COALESCE(SUM(precioFinal), 0) AS monto
     FROM reservas
     WHERE estadoReserva = 'confirmada'"
)->fetch(PDO::FETCH_ASSOC);

// Detalle de cada venta
$ventas = $conexion->query(
    "SELECT r.codReserva, r.fechaPago, r.precioFinal,
            u.nombre, u.apellido,
            v.codigoVuelo, v.origen, v.destino,
            a.nombre AS aerolinea
     FROM reservas r
     INNER JOIN usuarios u ON u.codUsuario = r.codUsuario
     INNER JOIN vuelos v ON v.codVuelo = r.codVuelo
     INNER JOIN aerolineas a ON a.codAerolinea = v.codAerolinea
     WHERE r.estadoReserva = 'confirmada'
     ORDER BY r.fechaPago DESC"
)->fetchAll(PDO::FETCH_ASSOC);

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
                    <th>Fecha</th>
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