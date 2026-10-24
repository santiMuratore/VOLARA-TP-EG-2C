<?php
require_once __DIR__ . '/../../../Includes/functions.php';
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

$clasesEstado = [
    'pendiente' => 'badge-pending',
    'aprobada'  => 'badge-approved',
    'denegada'  => 'badge-denied',
];

$titulo = 'Mis promociones';
require_once __DIR__ . '/../../../Includes/header.php';
require_once __DIR__ . '/../../../Includes/navbar.php';
?>

<main class="container my-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <h1 class="mb-0">Mis promociones</h1>
        <a class="btn-volara" href="altaPromocion.php">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> Crear promoción
        </a>
    </div>

    <?php if (empty($promociones)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="bi bi-tag" aria-hidden="true"></i></div>
            <p>Todavía no creaste ninguna promoción.</p>
        </div>
    <?php else: ?>
        <div class="volara-table-responsive">
            <table class="volara-table">
                <thead>
                    <tr>
                        <th scope="col">Descripción</th>
                        <th scope="col">Descuento</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($promociones as $promocion): ?>
                        <tr>
                            <td><?= htmlspecialchars($promocion['descripcion']) ?></td>
                            <td><?= htmlspecialchars($promocion['descuento']) ?>%</td>
                            <td>
                                <span class="volara-badge <?= $clasesEstado[$promocion['estado']] ?? 'badge-neutral' ?>">
                                    <?= htmlspecialchars($promocion['estado']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a class="table-action-btn"
                                       href="modificarPromocion.php?id=<?= (int) $promocion['codPromocion'] ?>"
                                       aria-label="Modificar promoción" title="Modificar">
                                        <i class="bi bi-pencil" aria-hidden="true"></i>
                                    </a>
                                    <a class="table-action-btn danger"
                                       href="eliminarPromocion.php?id=<?= (int) $promocion['codPromocion'] ?>"
                                       aria-label="Eliminar promoción" title="Eliminar">
                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <nav aria-label="Paginación de promociones">
            <div class="volara-pagination" >
                <?php if ($pagina > 1): ?>
                    <a href="?pagina=<?= $pagina - 1 ?>">Anterior</a>
                <?php else: ?>
                    <span aria-disabled="true">Anterior</span>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                    <?php if ($i === $pagina): ?>
                        <span class="active" aria-current="page"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?pagina=<?= $i ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($pagina < $totalPaginas): ?>
                    <a href="?pagina=<?= $pagina + 1 ?>" >Siguiente</a>
                <?php else: ?>
                    <span aria-disabled="true">Siguiente</span>
                <?php endif; ?>
            </div>
        </nav>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../../../Includes/footer.php'; ?>