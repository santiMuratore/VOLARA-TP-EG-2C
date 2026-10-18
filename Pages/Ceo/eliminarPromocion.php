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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $consulta = $conexion->prepare(
        "DELETE FROM promociones
         WHERE codPromocion = :codPromocion
           AND codAerolinea = :codAerolinea"
    );
    $consulta->execute([
        'codPromocion' => $codPromocion,
        'codAerolinea' => $_SESSION['codAerolinea'],
    ]);
    header('Location: promociones.php');
    exit;
}

$titulo = 'Eliminar promoción';
require_once __DIR__ . '/../Includes/header.php';
?>

<main class="container my-4">
    <h1>Eliminar promoción</h1>
    <p>¿Seguro que querés eliminar esta promoción?</p>
    <p>
        <strong><?= htmlspecialchars($promocion['descripcion']) ?></strong>
        (<?= htmlspecialchars($promocion['descuento']) ?>%, <?= htmlspecialchars($promocion['estado']) ?>)
    </p>

    <form method="post">
        <button type="submit" class="btn btn-danger">Sí, eliminar</button>
        <a href="promociones.php" class="btn btn-secondary">Cancelar</a>
    </form>
</main>

<?php require_once __DIR__ . '/../Includes/footer.php'; ?>