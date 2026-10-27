<?php
require_once __DIR__ . '/../../Includes/functions.php';
require_once __DIR__ . '/../../Includes/reportes.php';
$conexion = conectarDB();

$origenes = $conexion->query(
    "SELECT DISTINCT origen FROM vuelos ORDER BY origen"
)->fetchAll(PDO::FETCH_COLUMN);
$destinos = $conexion->query(
    "SELECT DISTINCT destino FROM vuelos ORDER BY destino"
)->fetchAll(PDO::FETCH_COLUMN);
$aerolineas = $conexion->query(
    "SELECT codAerolinea, nombre FROM aerolineas ORDER BY nombre"
)->fetchAll(PDO::FETCH_ASSOC);
$codigosAerolinea = array_map('intval', array_column($aerolineas, 'codAerolinea'));

$errores = [];
$origen = $_GET['origen'] ?? '';
$destino = $_GET['destino'] ?? '';
$fecha = trim($_GET['fecha'] ?? '');
$codAerolinea = (int) ($_GET['aerolinea'] ?? 0);
$precioMin = trim($_GET['precio_min'] ?? '');
$precioMax = trim($_GET['precio_max'] ?? '');

if ($origen !== '' && !in_array($origen, $origenes, true)) {
    $errores[] = 'El origen elegido no es válido.';
}
if ($destino !== '' && !in_array($destino, $destinos, true)) {
    $errores[] = 'El destino elegido no es válido.';
}
if ($origen !== '' && $origen === $destino) {
    $errores[] = 'El origen y el destino no pueden ser iguales.';
}
if ($fecha !== '') {
    if (!fechaValida($fecha)) {
        $errores[] = 'La fecha no es válida.';
    } elseif ($fecha < date('Y-m-d')) {
        $errores[] = 'La fecha no puede ser anterior a hoy.';
    }
}
if ($codAerolinea !== 0 && !in_array($codAerolinea, $codigosAerolinea, true)) {
    $errores[] = 'La aerolínea elegida no es válida.';
}
if ($precioMin !== '' && (!is_numeric($precioMin) || $precioMin < 0)) {
    $errores[] = 'El precio mínimo debe ser un número mayor o igual a 0.';
}
if ($precioMax !== '' && (!is_numeric($precioMax) || $precioMax < 0)) {
    $errores[] = 'El precio máximo debe ser un número mayor o igual a 0.';
}
if (is_numeric($precioMin) && is_numeric($precioMax) && $precioMin > $precioMax) {
    $errores[] = 'El precio mínimo no puede ser mayor que el máximo.';
}

$vuelos = [];

if (empty($errores)) {
    $where = [
        'TIMESTAMP(v.fecha, v.hora) > NOW()',
        'v.asientosDisponibles > 0',
    ];
    $params = [];

    if ($origen !== '') {
        $where[] = 'v.origen = :origen';
        $params['origen'] = $origen;
    }
    if ($destino !== '') {
        $where[] = 'v.destino = :destino';
        $params['destino'] = $destino;
    }
    if ($fecha !== '') {
        $where[] = 'v.fecha = :fecha';
        $params['fecha'] = $fecha;
    }
    if ($codAerolinea > 0) {
        $where[] = 'v.codAerolinea = :codAerolinea';
        $params['codAerolinea'] = $codAerolinea;
    }
    if ($precioMin !== '') {
        $where[] = 'v.precio >= :precioMin';
        $params['precioMin'] = $precioMin;
    }
    if ($precioMax !== '') {
        $where[] = 'v.precio <= :precioMax';
        $params['precioMax'] = $precioMax;
    }
    $condiciones = implode(' AND ', $where);

    $consulta = $conexion->prepare(
        "SELECT v.codVuelo, v.codigoVuelo, v.origen, v.destino, v.fecha, v.hora,
                v.precio, v.clase, v.asientosDisponibles,
                a.nombre AS aerolinea
         FROM vuelos v
         INNER JOIN aerolineas a ON a.codAerolinea = v.codAerolinea
         WHERE $condiciones
         ORDER BY v.fecha, v.hora, v.precio"
    );
    $consulta->execute($params);
    $vuelos = $consulta->fetchAll(PDO::FETCH_ASSOC);
}

$titulo = 'Buscar vuelos';
require_once __DIR__ . '/../../Includes/navbar.php';
require_once __DIR__ . '/../../Includes/header.php';
?>

<main class="container my-4">
    <h1>Buscar vuelos</h1>

    <form method="get" class="row g-3 align-items-end mb-4">
        <div class="col-md-3">
            <label for="origen" class="form-label">Origen</label>
            <select class="form-select" id="origen" name="origen">
                <option value="">Cualquiera</option>
                <?php foreach ($origenes as $o): ?>
                    <option value="<?= htmlspecialchars($o) ?>" <?= $origen === $o ? 'selected' : '' ?>>
                        <?= htmlspecialchars($o) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="destino" class="form-label">Destino</label>
            <select class="form-select" id="destino" name="destino">
                <option value="">Cualquiera</option>
                <?php foreach ($destinos as $d): ?>
                    <option value="<?= htmlspecialchars($d) ?>" <?= $destino === $d ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="fecha" class="form-label">Fecha</label>
            <input type="date" class="form-control" id="fecha" name="fecha"
                   min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($fecha) ?>">
        </div>
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
        <div class="col-md-3">
            <label for="precio_min" class="form-label">Precio mínimo</label>
            <input type="number" class="form-control" id="precio_min" name="precio_min"
                   min="0" step="1000" value="<?= htmlspecialchars($precioMin) ?>">
        </div>
        <div class="col-md-3">
            <label for="precio_max" class="form-label">Precio máximo</label>
            <input type="number" class="form-control" id="precio_max" name="precio_max"
                   min="0" step="1000" value="<?= htmlspecialchars($precioMax) ?>">
        </div>
        <div class="col-md-6">
            <button type="submit" class="btn btn-primary">Buscar</button>
            <a href="buscarVuelos.php" class="btn btn-secondary">Limpiar</a>
        </div>
    </form>

    <?php foreach ($errores as $error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endforeach; ?>

    <?php if (empty($errores)): ?>
        <?php if (empty($vuelos)): ?>
            <div class="alert alert-info">No encontramos vuelos con esos criterios.</div>
        <?php else: ?>
            <p><strong><?= count($vuelos) ?></strong> vuelo(s) encontrado(s)</p>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Vuelo</th>
                        <th>Aerolínea</th>
                        <th>Ruta</th>
                        <th>Salida</th>
                        <th>Clase</th>
                        <th>Precio</th>
                        <th>Asientos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vuelos as $vuelo): ?>
                        <tr>
                            <td><?= htmlspecialchars($vuelo['codigoVuelo']) ?></td>
                            <td><?= htmlspecialchars($vuelo['aerolinea']) ?></td>
                            <td><?= htmlspecialchars($vuelo['origen'] . ' → ' . $vuelo['destino']) ?></td>
                            <td><?= htmlspecialchars($vuelo['fecha'] . ' ' . substr($vuelo['hora'], 0, 5)) ?></td>
                            <td><?= htmlspecialchars(ucfirst($vuelo['clase'])) ?></td>
                            <td>$<?= number_format((float) $vuelo['precio'], 2, ',', '.') ?></td>
                            <td><?= (int) $vuelo['asientosDisponibles'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../../Includes/footer.php'; ?>