<?php
require_once __DIR__ . '/../Includes/functions.php';
requerirRol('ceo aerolínea');

$conexion = conectarDB();
$consultaTotal = $conexion->prepare(
    "SELECT COUNT(*) FROM promociones WHERE codAerolinea = :codAerolinea"
);
$consultaTotal->execute(['codAerolinea' => $_SESSION['codAerolinea']]);
$totalPromociones = (int) $consultaTotal->fetchColumn();
$porPagina = 5;
$pagina = isset($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
$totalPaginas = max(1, (int) ceil($totalPromociones / $porPagina));

if ($pagina < 1) {
    $pagina = 1;
}
if ($pagina > $totalPaginas) {
    $pagina = $totalPaginas;
}

$offset = ($pagina - 1) * $porPagina;
$sql = "SELECT codPromocion, descripcion, descuento, estado
        FROM promociones
        WHERE codAerolinea = :codAerolinea
        ORDER BY codPromocion DESC
        LIMIT :limite OFFSET :offset";
$consulta = $conexion->prepare($sql);
$consulta->bindValue(':codAerolinea', $_SESSION['codAerolinea'], PDO::PARAM_INT);
$consulta->bindValue(':limite', $porPagina, PDO::PARAM_INT);
$consulta->bindValue(':offset', $offset, PDO::PARAM_INT);
$consulta->execute();
$promociones = $consulta->fetchAll(PDO::FETCH_ASSOC);

$titulo = 'Mis promociones';
require_once __DIR__ . '/../Includes/header.php';
?>

<main class="container my-4">
    <h1>Mis promociones</h1>
    <?php if (empty($promociones)): ?>
        <div class="alert alert-info">Todavía no creaste ninguna promoción.</div>
    <?php else: ?>
    <table class="table table-striped">
    <thead>
        <tr>
            <th>Descripción</th>
            <th>Descuento</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($promociones as $promocion): ?>
            <tr>
                <td><?= htmlspecialchars($promocion['descripcion']) ?></td>
                <td><?= htmlspecialchars($promocion['descuento']) ?>%</td>
                <td><?= htmlspecialchars($promocion['estado']) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<nav aria-label="Paginación de promociones">
    <ul class="pagination">
        <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?pagina=<?= $pagina - 1 ?>">Anterior</a>
        </li>

        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
            <li class="page-item <?= $i === $pagina ? 'active' : '' ?>">
                <a class="page-link" href="?pagina=<?= $i ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>

        <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>">
            <a class="page-link" href="?pagina=<?= $pagina + 1 ?>">Siguiente</a>
        </li>
    </ul>
</nav>
<?php endif; ?>
</main>

<?php require_once __DIR__ . '/../Includes/footer.php'; ?>