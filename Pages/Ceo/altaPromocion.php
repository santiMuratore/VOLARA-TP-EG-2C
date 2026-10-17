<?php
require_once __DIR__ . '/../Includes/functions.php';
requerirRol('ceo aerolínea');
$errores = [];
$descripcion = '';
$descuento = '';

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
        $conexion = conectarDB();
        $sql = "INSERT INTO promociones (codAerolinea, descripcion, descuento, estado)
                VALUES (:codAerolinea, :descripcion, :descuento, 'pendiente')";
        $consulta = $conexion->prepare($sql);
        $consulta->execute([
            'codAerolinea' => $_SESSION['codAerolinea'],
            'descripcion'  => $descripcion,
            'descuento'    => $descuento,
        ]);
        header('Location: promociones.php');
        exit;
    }
}
$titulo = 'Nueva promoción';
require_once __DIR__ . '/../Includes/header.php';
?>

<main class="container my-4">
    <h1>Nueva promoción</h1>
    <?php foreach ($errores as $error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endforeach; ?>
    <form method="post">
        <div class="mb-3">
            <label for="descripcion" class="form-label">Descripción</label>
            <input type="text" class="form-control" id="descripcion" name="descripcion"
                   maxlength="255" required>
        </div>
        <div class="mb-3">
            <label for="descuento" class="form-label">Descuento (%)</label>
            <input type="number" class="form-control" id="descuento" name="descuento"
                   min="1" max="100" step="0.5" required>
        </div>
        <button type="submit" class="btn btn-primary">Crear promoción</button>
        <a href="promociones.php" class="btn btn-secondary">Volver</a>
    </form>
</main>

<?php require_once __DIR__ . '/../Includes/footer.php'; ?>
