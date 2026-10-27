<?php
require_once __DIR__ . '/../../../Includes/functions.php';
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
require_once __DIR__ . '/../../../Includes/header.php';
require_once __DIR__ . '/../../../Includes/navbar.php';
?>

<main class="volara-main">
    <div class="container my-auto py-5">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">
                <h1 class="text-center mb-4">Nueva promoción</h1>

                <?php foreach ($errores as $error): ?>
                    <div class="volara-alert alert-danger mb-3" role="alert"><?= htmlspecialchars($error) ?></div>
                <?php endforeach; ?>

                <form method="post" class="volara-card">
                    <div class="form-group mb-3">
                        <label for="descripcion" class="volara-label">Descripción</label>
                        <input type="text" class="volara-input" id="descripcion" name="descripcion"
                               maxlength="255" required value="<?= htmlspecialchars($descripcion) ?>">
                    </div>
                    <div class="form-group mb-4">
                        <label for="descuento" class="volara-label">Descuento (%)</label>
                        <input type="number" class="volara-input" id="descuento" name="descuento"
                               min="1" max="100" step="0.5" required value="<?= htmlspecialchars($descuento) ?>">
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn-volara">Crear promoción</button>
                        <a href="promociones.php" class="btn-volara-outline">Volver</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../../Includes/footer.php'; ?>
