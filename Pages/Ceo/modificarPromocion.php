<?php
require_once __DIR__ . '/../Includes/functions.php';
requerirRol('ceo aerolínea');

$codPromocion = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$conexion = conectarDB();
$consulta = $conexion->prepare(
    "SELECT codPromocion, descripcion, descuento, estado
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

$errores = [];
$descripcion = $promocion['descripcion'];
$descuento = $promocion['descuento'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $descripcion = trim($_POST['descripcion'] ?? '');
    $descuento = trim($_POST['descuento'] ?? '');

    if ($descripcion === '' || strlen($descripcion) > 255) {
        $errores[] = 'La descripción es obligatoria y puede tener hasta 255 caracteres.';
    }
    if (!is_numeric($descuento) || $descuento < 1 || $descuento > 100) {
        $errores[] = 'El descuento debe ser un número entre 1 y 100.';
    }

    if (empty($errores)) {
        $consulta = $conexion->prepare(
            "UPDATE promociones
             SET descripcion = :descripcion,
                 descuento = :descuento,
                 estado = 'pendiente'
             WHERE codPromocion = :codPromocion
               AND codAerolinea = :codAerolinea"
        );
        $consulta->execute([
            'descripcion'  => $descripcion,
            'descuento'    => $descuento,
            'codPromocion' => $codPromocion,
            'codAerolinea' => $_SESSION['codAerolinea'],
        ]);
        header('Location: promociones.php');
        exit;
    }
}

$titulo = 'Modificar promoción';
require_once __DIR__ . '/../Includes/header.php';
?>

<main class="container my-4">
    <h1>Modificar promoción</h1>
    <?php foreach ($errores as $error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endforeach; ?>

    <form method="post">
        <div class="mb-3">
            <label for="descripcion" class="form-label">Descripción</label>
            <input type="text" class="form-control" id="descripcion" name="descripcion"
                maxlength="255" required value="<?= htmlspecialchars($descripcion) ?>">
        </div>
        <div class="mb-3">
            <label for="descuento" class="form-label">Descuento (%)</label>
            <input type="number" class="form-control" id="descuento" name="descuento"
                min="1" max="100" step="0.01" required value="<?= htmlspecialchars($descuento) ?>">
        </div>
        <button type="submit" class="btn btn-primary">Guardar cambios</button>
        <a href="promociones.php" class="btn btn-secondary">Volver</a>
    </form>
</main>

<?php require_once __DIR__ . '/../Includes/footer.php'; ?>