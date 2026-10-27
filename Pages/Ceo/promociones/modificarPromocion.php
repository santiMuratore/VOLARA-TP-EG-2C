<?php
require_once __DIR__ . '/../../../Includes/functions.php';
require_once __DIR__ . '/../../../Includes/reportes.php';
requerirRol('ceo aerolínea');

$codPromocion = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$conexion = conectarDB();
$consulta = $conexion->prepare(
    "SELECT codPromocion, descripcion, descuento, destino, fechaDesde, fechaHasta, estado
     FROM promociones
     WHERE codPromocion = :codPromocion
       AND codAerolinea = :codAerolinea"
);
$consulta->execute([
    'codPromocion' => $codPromocion,
    'codAerolinea' => $_SESSION['codAerolinea'],
]);
$promocion = $consulta->fetch(PDO::FETCH_ASSOC);

if (!$promocion) {
    header('Location: promociones.php');
    exit;
}

// Destinos de los vuelos activos de la aerolínea del CEO
$consulta = $conexion->prepare(
    "SELECT DISTINCT destino FROM vuelos
     WHERE codAerolinea = :codAerolinea AND activo = 1
     ORDER BY destino"
);
$consulta->execute(['codAerolinea' => $_SESSION['codAerolinea']]);
$destinos = $consulta->fetchAll(PDO::FETCH_COLUMN);

// Si el destino guardado ya no tiene vuelos activos, se conserva como opción
if ($promocion['destino'] !== null && !in_array($promocion['destino'], $destinos, true)) {
    $destinos[] = $promocion['destino'];
}

$errores = [];
$descripcion = $promocion['descripcion'];
$descuento = $promocion['descuento'];
$destino = $promocion['destino'] ?? '';
$fechaDesde = $promocion['fechaDesde'] ?? '';
$fechaHasta = $promocion['fechaHasta'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $descripcion = trim($_POST['descripcion'] ?? '');
    $descuento = trim($_POST['descuento'] ?? '');
    $destino = trim($_POST['destino'] ?? '');
    $fechaDesde = trim($_POST['fechaDesde'] ?? '');
    $fechaHasta = trim($_POST['fechaHasta'] ?? '');

    if ($descripcion === '' || strlen($descripcion) > 255) {
        $errores[] = 'La descripción es obligatoria y puede tener hasta 255 caracteres.';
    }
    if (!is_numeric($descuento) || $descuento < 1 || $descuento > 100) {
        $errores[] = 'El descuento debe ser un número entre 1 y 100.';
    }
    if ($destino !== '' && !in_array($destino, $destinos, true)) {
        $errores[] = 'El destino elegido no corresponde a ninguno de tus vuelos.';
    }

    $desdeOk = $fechaDesde === '' || fechaValida($fechaDesde);
    $hastaOk = $fechaHasta === '' || fechaValida($fechaHasta);
    if (!$desdeOk) {
        $errores[] = 'La fecha "desde" no es válida.';
    }
    if (!$hastaOk) {
        $errores[] = 'La fecha "hasta" no es válida.';
    }
    if ($desdeOk && $hastaOk) {
        if ($fechaDesde !== '' && $fechaHasta !== '' && $fechaDesde > $fechaHasta) {
            $errores[] = 'La fecha "desde" no puede ser posterior a la fecha "hasta".';
        } elseif ($fechaHasta !== '' && $fechaHasta !== ($promocion['fechaHasta'] ?? '') && $fechaHasta < date('Y-m-d')) {
            $errores[] = 'La fecha "hasta" no puede ser anterior a hoy.';
        }
    }

    if (empty($errores)) {
        $consulta = $conexion->prepare(
            "UPDATE promociones
             SET descripcion = :descripcion,
                 descuento = :descuento,
                 destino = :destino,
                 fechaDesde = :fechaDesde,
                 fechaHasta = :fechaHasta,
                 estado = 'pendiente'
             WHERE codPromocion = :codPromocion
               AND codAerolinea = :codAerolinea"
        );
        $consulta->execute([
            'descripcion'  => $descripcion,
            'descuento'    => $descuento,
            'destino'      => $destino !== '' ? $destino : null,
            'fechaDesde'   => $fechaDesde !== '' ? $fechaDesde : null,
            'fechaHasta'   => $fechaHasta !== '' ? $fechaHasta : null,
            'codPromocion' => $codPromocion,
            'codAerolinea' => $_SESSION['codAerolinea'],
        ]);
        header('Location: promociones.php');
        exit;
    }
}

$titulo = 'Modificar promoción';
require_once __DIR__ . '/../../../Includes/header.php';
require_once __DIR__ . '/../../../Includes/navbar.php';
?>

<main id="contenido-principal" class="container my-4">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <h1>Modificar promoción</h1>

            <?php if ($promocion['estado'] === 'aprobada'): ?>
                <div class="volara-alert alert-warning" role="status">
                    Esta promoción está aprobada. Al guardar los cambios vuelve a quedar pendiente
                    y el administrador tiene que aprobarla de nuevo.
                </div>
            <?php endif; ?>

            <?php foreach ($errores as $error): ?>
                <div class="volara-alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>

            <form method="post" class="volara-card-flat">
                <div class="form-group">
                    <label for="descripcion" class="volara-label">Descripción</label>
                    <input type="text" class="volara-input" id="descripcion" name="descripcion"
                           maxlength="255" required value="<?= htmlspecialchars($descripcion) ?>">
                </div>

                <div class="form-group">
                    <label for="descuento" class="volara-label">Descuento (%)</label>
                    <input type="number" class="volara-input" id="descuento" name="descuento"
                           min="1" max="100" step="0.01" required value="<?= htmlspecialchars($descuento) ?>">
                </div>

                <div class="form-group">
                    <label for="destino" class="volara-label">Destino (opcional)</label>
                    <select class="volara-select" id="destino" name="destino" aria-describedby="ayuda-destino">
                        <option value="">Todos los destinos</option>
                        <?php foreach ($destinos as $d): ?>
                            <option value="<?= htmlspecialchars($d) ?>" <?= $destino === $d ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="ayuda-destino" class="text-muted small mt-1">
                        Solo aparecen los destinos de tus vuelos activos.
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6 form-group">
                        <label for="fechaDesde" class="volara-label">Vigente desde (opcional)</label>
                        <input type="date" class="volara-input" id="fechaDesde" name="fechaDesde"
                               min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($fechaDesde) ?>">
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="fechaHasta" class="volara-label">Vigente hasta (opcional)</label>
                        <input type="date" class="volara-input" id="fechaHasta" name="fechaHasta"
                               min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($fechaHasta) ?>">
                    </div>
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn-volara">Guardar cambios</button>
                    <a href="promociones.php" class="btn-volara-outline">Volver</a>
                </div>
            </form>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../../Includes/footer.php'; ?>