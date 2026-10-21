<?php
require_once __DIR__ . '/../Includes/functions.php';
require_once __DIR__ . '/../Includes/reportes.php';
requerirRol('administrador');

$conexion = conectarDB();

$aerolineas = $conexion->query(
    "SELECT codAerolinea, nombre FROM aerolineas ORDER BY nombre"
)->fetchAll(PDO::FETCH_ASSOC);

$clases = ['economica', 'premium', 'business'];

$errores = [];
$codAerolinea = (int) ($_GET['aerolinea'] ?? 0);
$clase = $_GET['clase'] ?? '';
$desde = trim($_GET['desde'] ?? '');
$hasta = trim($_GET['hasta'] ?? '');

if ($clase !== '' && !in_array($clase, $clases, true)) {
    $errores[] = 'La clase elegida no es válida.';
}
if ($desde !== '' && !fechaValida($desde)) {
    $errores[] = 'La fecha "desde" no es válida.';
}
if ($hasta !== '' && !fechaValida($hasta)) {
    $errores[] = 'La fecha "hasta" no es válida.';
}
if (empty($errores) && $desde !== '' && $hasta !== '' && $desde > $hasta) {
    $errores[] = 'La fecha "desde" no puede ser posterior a la fecha "hasta".';
}

$totales = ['cantidad' => 0, 'asientos' => 0, 'disponibles' => 0];
$vuelos = [];

if (empty($errores)) {
    $where = ['1 = 1'];
    $params = [];

    if ($codAerolinea > 0) {
        $where[] = 'v.codAerolinea = :codAerolinea';
        $params['codAerolinea'] = $codAerolinea;
    }
    if ($clase !== '') {
        $where[] = 'v.clase = :clase';
        $params['clase'] = $clase;
    }
    if ($desde !== '') {
        $where[] = 'v.fecha >= :desde';
        $params['desde'] = $desde;
    }
    if ($hasta !== '') {
        $where[] = 'v.fecha <= :hasta';
        $params['hasta'] = $hasta;
    }
    $condiciones = implode(' AND ', $where);

    $consulta = $conexion->prepare(
        "SELECT COUNT(*) AS cantidad,
                COALESCE(SUM(v.asientosTotal), 0) AS asientos,
                COALESCE(SUM(v.asientosDisponibles), 0) AS disponibles
         FROM vuelos v
         WHERE $condiciones"
    );
    $consulta->execute($params);
    $totales = $consulta->fetch(PDO::FETCH_ASSOC);

    $consulta = $conexion->prepare(
        "SELECT v.codigoVuelo, v.origen, v.destino, v.fecha, v.hora,
                v.precio, v.clase, v.asientosTotal, v.asientosDisponibles,
                a.nombre AS aerolinea
         FROM vuelos v
         INNER JOIN aerolineas a ON a.codAerolinea = v.codAerolinea
         WHERE $condiciones
         ORDER BY v.fecha, v.hora"
    );
    $consulta->execute($params);
    $vuelos = $consulta->fetchAll(PDO::FETCH_ASSOC);
}

$titulo = 'Reporte de vuelos';
require_once __DIR__ . '/../Includes/header.php';
?>

<main class="container my-4">
    <div class="d-flex justify-content-between align-items-center">
        <h1>Reporte de vuelos</h1>
        <button type="button" class="btn btn-outline-secondary d-print-none"
                onclick="window.print()">Imprimir / Guardar PDF</button>
    </div>
    <p class="d-none d-print-block">Emitido el <?= date('d/m/Y H:i') ?></p>

    <form method="get" class="row g-3 align-items-end mb-4 d-print-none">
        <div class="col-md-3">
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
        <div class="col-md-2">
            <label for="clase" class="form-label">Clase</label>
            <select class="form-select" id="clase" name="clase">
                <option value="">Todas</option>
                <?php foreach ($clases as $c): ?>
                    <option value="<?= $c ?>" <?= $clase === $c ? 'selected' : '' ?>><?= ucfirst($c) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label for="desde" class="form-label">Desde</label>
            <input type="date" class="form-control" id="desde" name="desde"
                   value="<?= htmlspecialchars($desde) ?>">
        </div>
        <div class="col-md-2">
            <label for="hasta" class="form-label">Hasta</label>
            <input type="date" class="form-control" id="hasta" name="hasta"
                   value="<?= htmlspecialchars($hasta) ?>">
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <a href="reporteVuelos.php" class="btn btn-secondary">Limpiar</a>
        </div>
    </form>

    <?php foreach ($errores as $error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endforeach; ?>

    <p>
        <strong>Vuelos:</strong> <?= (int) $totales['cantidad'] ?> |
        <strong>Asientos totales:</strong> <?= (int) $totales['asientos'] ?> |
        <strong>Disponibles:</strong> <?= (int) $totales['disponibles'] ?>
    </p>

    <?php if (empty($vuelos)): ?>
        <div class="alert alert-info">No hay vuelos para los filtros elegidos.</div>
    <?php else: ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Vuelo</th>
                    <th>Aerolínea</th>
                    <th>Ruta</th>
                    <th>Fecha y hora</th>
                    <th>Clase</th>
                    <th>Precio</th>
                    <th>Ocupación</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vuelos as $vuelo): ?>
                    <?php
                    $ocupados = (int) $vuelo['asientosTotal'] - (int) $vuelo['asientosDisponibles'];
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($vuelo['codigoVuelo']) ?></td>
                        <td><?= htmlspecialchars($vuelo['aerolinea']) ?></td>
                        <td><?= htmlspecialchars($vuelo['origen'] . ' → ' . $vuelo['destino']) ?></td>
                        <td><?= htmlspecialchars($vuelo['fecha'] . ' ' . $vuelo['hora']) ?></td>
                        <td><?= htmlspecialchars(ucfirst($vuelo['clase'])) ?></td>
                        <td>$<?= number_format((float) $vuelo['precio'], 2, ',', '.') ?></td>
                        <td><?= $ocupados ?> / <?= (int) $vuelo['asientosTotal'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../Includes/footer.php'; ?>