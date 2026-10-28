<?php
require_once __DIR__ . '/../../../Includes/functions.php';
require_once __DIR__ . '/../../../Includes/reportes.php';
requerirRol('ceo aerolínea');

$conexion = conectarDB();

// Destinos de los vuelos activos de la aerolínea del CEO
$consulta = $conexion->prepare(
    "SELECT DISTINCT destino FROM vuelos
     WHERE codAerolinea = :codAerolinea AND activo = 1
     ORDER BY destino"
);
$consulta->execute(['codAerolinea' => $_SESSION['codAerolinea']]);
$destinos = $consulta->fetchAll(PDO::FETCH_COLUMN);

$errores = [];
$descripcion = '';
$descuento = '';
$destino = '';
$fechaDesde = '';
$fechaHasta = '';

if (empty($_SESSION['csrf_alta_promocion'])) {
    $_SESSION['csrf_alta_promocion'] =
        bin2hex(random_bytes(32));
}

$csrfToken =
    $_SESSION['csrf_alta_promocion'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $descripcion = trim($_POST['descripcion'] ?? '');
    $descuento = trim($_POST['descuento'] ?? '');
    $destino = trim($_POST['destino'] ?? '');
    $fechaDesde = trim($_POST['fechaDesde'] ?? '');
    $fechaHasta = trim($_POST['fechaHasta'] ?? '');
    $csrfRecibido =
        $_POST['csrf_token'] ?? '';

        if (
            !hash_equals(
                $csrfToken,
                (string) $csrfRecibido
            )
        ) {
            $errores[] =
                'La solicitud no es válida. Recargá la página e intentá nuevamente.';
        }

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
        } elseif ($fechaHasta !== '' && $fechaHasta < date('Y-m-d')) {
            $errores[] = 'La fecha "hasta" no puede ser anterior a hoy.';
        }
    }

    if (empty($errores)) {
        $sql = "INSERT INTO promociones
                    (codAerolinea, descripcion, descuento, destino, fechaDesde, fechaHasta, estado)
                VALUES
                    (:codAerolinea, :descripcion, :descuento, :destino, :fechaDesde, :fechaHasta, 'pendiente')";
        try {
            $consulta = $conexion->prepare($sql);
            $consulta->execute([
                'codAerolinea' => $_SESSION['codAerolinea'],
                'descripcion'  => $descripcion,
                'descuento'    => $descuento,
                'destino'      => $destino !== '' ? $destino : null,
                'fechaDesde'   => $fechaDesde !== '' ? $fechaDesde : null,
                'fechaHasta'   => $fechaHasta !== '' ? $fechaHasta : null,
            ]);

            $_SESSION['flash_promociones'] = [
                'tipo'  => 'success',
                'texto' => 'La promoción se creó correctamente. Queda pendiente hasta que el administrador la apruebe.',
            ];
        } catch (PDOException $e) {
            error_log('VOLARA - Error al crear promoción: ' . $e->getMessage());
            $_SESSION['flash_promociones'] = [
                'tipo'  => 'danger',
                'texto' => 'No se pudo crear la promoción. Intentá nuevamente.',
            ];
        }

        $_SESSION['csrf_alta_promocion'] =
            bin2hex(random_bytes(32));
        header('Location: promociones.php');
        exit;
    }
}

$titulo = 'Nueva promoción';
require_once __DIR__ . '/../../../Includes/header.php';
require_once __DIR__ . '/../../../Includes/navbar.php';
?>

<main id="contenido-principal" class="container my-4">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <h1>Nueva promoción</h1>
            <p class="text-muted">
                Queda pendiente hasta que el administrador la apruebe. El destino y las fechas son
                opcionales: si los dejás vacíos, vale para todos tus vuelos y sin límite de fechas.
            </p>

            <?php foreach ($errores as $error): ?>
                <div class="volara-alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>

            <form method="post" class="volara-card-flat">
                <p class="required-help">
                    Los campos marcados con
                    <span class="required-mark" aria-hidden="true">*</span>
                    son obligatorios.
                </p>
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $csrfToken,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >
                <div class="form-group">
                    <label for="descripcion" class="volara-label">Descripción<span class="required-mark" aria-hidden="true">*</span></label>
                    <input type="text" class="volara-input" id="descripcion" name="descripcion"
                           maxlength="255" required value="<?= htmlspecialchars($descripcion) ?>">
                </div>

                <div class="form-group">
                    <label for="descuento" class="volara-label">Descuento (%)<span class="required-mark" aria-hidden="true">*</span></label>
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
                    <button type="submit" class="btn-volara">Crear promoción</button>
                    <a href="promociones.php" class="btn-volara-outline">Volver</a>
                </div>
            </form>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../../Includes/footer.php'; ?>