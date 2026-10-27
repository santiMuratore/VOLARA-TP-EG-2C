<?php
require_once __DIR__ . '/../../../Includes/functions.php';
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

$clasesEstado = [
    'pendiente' => 'badge-pending',
    'aprobada'  => 'badge-approved',
    'denegada'  => 'badge-denied',
];

$titulo = 'Eliminar promoción';
require_once __DIR__ . '/../../../Includes/header.php';
require_once __DIR__ . '/../../../Includes/navbar.php';
?>

<main class="container my-4">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <h1>Eliminar promoción</h1>
            <p>¿Seguro que querés eliminar esta promoción? No se puede deshacer.</p>

            <div class="volara-card-flat mb-4">
                <p class="mb-2"><strong><?= htmlspecialchars($promocion['descripcion']) ?></strong></p>
                <p class="mb-0">
                    <?= htmlspecialchars($promocion['descuento']) ?>%
                    <span class="volara-badge <?= $clasesEstado[$promocion['estado']] ?? 'badge-neutral' ?> ms-2">
                        <?= htmlspecialchars($promocion['estado']) ?>
                    </span>
                </p>
            </div>

            <form method="post" class="d-flex gap-2 flex-wrap">
                <button type="submit" class="btn-volara">Sí, eliminar</button>
                <a href="promociones.php" class="btn-volara-outline">Cancelar</a>
            </form>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../../Includes/footer.php'; ?>