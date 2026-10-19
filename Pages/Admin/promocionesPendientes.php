<?php
require_once __DIR__ . '/../Includes/functions.php';
requerirRol('administrador');

$conexion = conectarDB();

// 1) Total de pendientes (de todas las aerolíneas)
$totalPendientes = (int) $conexion
    ->query("SELECT COUNT(*) FROM promociones WHERE estado = 'pendiente'")
    ->fetchColumn();

// 2) Página actual, total de páginas y offset
$porPagina = 5;
$pagina = isset($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
$totalPaginas = max(1, (int) ceil($totalPendientes / $porPagina));
$pagina = max(1, min($pagina, $totalPaginas));
$offset = ($pagina - 1) * $porPagina;

// 3) Solo las filas de esta página
$consulta = $conexion->prepare(
    "SELECT p.codPromocion, p.descripcion, p.descuento, a.nombre AS aerolinea
     FROM promociones p
     INNER JOIN aerolineas a ON a.codAerolinea = p.codAerolinea
     WHERE p.estado = 'pendiente'
     ORDER BY p.codPromocion DESC
     LIMIT :limite OFFSET :offset"
);
$consulta->bindValue(':limite', $porPagina, PDO::PARAM_INT);
$consulta->bindValue(':offset', $offset, PDO::PARAM_INT);
$consulta->execute();
$pendientes = $consulta->fetchAll(PDO::FETCH_ASSOC);

$titulo = 'Promociones pendientes';
require_once __DIR__ . '/../Includes/header.php';
?>

<main class="container my-4">
    <?php if (empty($pendientes)): ?>
        <div class="alert alert-info">No hay promociones pendientes de revisión.</div>
    <?php else: ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Aerolínea</th>
                    <th>Descripción</th>
                    <th>Descuento</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendientes as $promocion): ?>
                    <tr>
                        <td><?= htmlspecialchars($promocion['aerolinea']) ?></td>
                        <td><?= htmlspecialchars($promocion['descripcion']) ?></td>
                        <td><?= htmlspecialchars($promocion['descuento']) ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <nav aria-label="Paginación de promociones pendientes">
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