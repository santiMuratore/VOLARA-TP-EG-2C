<?php
require_once __DIR__ . '/../../../Includes/functions.php';
require_once __DIR__ . '/../../../Includes/reportes.php';
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
require_once __DIR__ . '/../../../Includes/header.php';
require_once __DIR__ . '/../../../Includes/navbar.php';
?>

<main class="container my-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h1 class="mb-0">Reporte de vuelos</h1>
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
                <label for="clase" class="volara-label">Clase</label>
                <select class="volara-select" id="clase" name="clase">
                    <option value="">Todas</option>
                    <?php foreach ($clases as $c): ?>
                        <option value="<?= $c ?>" <?= $clase === $c ? 'selected' : '' ?>><?= ucfirst($c) ?></option>
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
                <a href="reporteVuelos.php" class="btn-volara-outline">Limpiar</a>
            </div>
        </div>
    </form>

    <?php foreach ($errores as $error): ?>
        <div class="volara-alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endforeach; ?>

    <div class="dashboard-grid">
        <div class="stat-card">
            <div class="stat-icon stat-icon-red"><i class="bi bi-airplane" aria-hidden="true"></i></div>
            <div class="stat-value"><?= (int) $totales['cantidad'] ?></div>
            <div class="stat-label">Vuelos</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-dark"><i class="bi bi-people" aria-hidden="true"></i></div>
            <div class="stat-value"><?= (int) $totales['asientos'] ?></div>
            <div class="stat-label">Asientos totales</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-dark"><i class="bi bi-check2-square" aria-hidden="true"></i></div>
            <div class="stat-value"><?= (int) $totales['disponibles'] ?></div>
            <div class="stat-label">Asientos disponibles</div>
        </div>
    </div>

    <?php if (empty($vuelos)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="bi bi-airplane" aria-hidden="true"></i></div>
            <p>No hay vuelos para los filtros elegidos.</p>
        </div>
    <?php else: ?>
        <div class="volara-table-responsive">
            <table class="volara-table">
                <thead>
                    <tr>
                        <th scope="col">Vuelo</th>
                        <th scope="col">Aerolínea</th>
                        <th scope="col">Ruta</th>
                        <th scope="col">Fecha y hora</th>
                        <th scope="col">Clase</th>
                        <th scope="col">Precio</th>
                        <th scope="col">Ocupación</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vuelos as $vuelo): ?>
                        <?php $ocupados = (int) $vuelo['asientosTotal'] - (int) $vuelo['asientosDisponibles']; ?>
                        <tr>
                            <td><?= htmlspecialchars($vuelo['codigoVuelo']) ?></td>
                            <td><?= htmlspecialchars($vuelo['aerolinea']) ?></td>
                            <td><?= htmlspecialchars($vuelo['origen'] . ' → ' . $vuelo['destino']) ?></td>
                            <td><?= htmlspecialchars($vuelo['fecha'] . ' ' . substr($vuelo['hora'], 0, 5)) ?></td>
                            <td><span class="volara-badge badge-neutral"><?= htmlspecialchars(ucfirst($vuelo['clase'])) ?></span></td>
                            <td>$<?= number_format((float) $vuelo['precio'], 2, ',', '.') ?></td>
                            <td><?= $ocupados ?> / <?= (int) $vuelo['asientosTotal'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../../../Includes/footer.php'; ?>