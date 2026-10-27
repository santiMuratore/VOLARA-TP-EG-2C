<?php
require_once __DIR__ . '/../../../Includes/functions.php';
require_once __DIR__ . '/../../../Includes/funcionPromociones.php';
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
$pagina = max(1, min($pagina, $totalPaginas));
$offset = ($pagina - 1) * $porPagina;

$consulta = $conexion->prepare(
    "SELECT codPromocion, descripcion, descuento, destino, fechaDesde, fechaHasta, estado
     FROM promociones
     WHERE codAerolinea = :codAerolinea
     ORDER BY codPromocion DESC
     LIMIT :limite OFFSET :offset"
);
$consulta->bindValue(':codAerolinea', $_SESSION['codAerolinea'], PDO::PARAM_INT);
$consulta->bindValue(':limite', $porPagina, PDO::PARAM_INT);
$consulta->bindValue(':offset', $offset, PDO::PARAM_INT);
$consulta->execute();
$promociones = $consulta->fetchAll(PDO::FETCH_ASSOC);

$hoy = date('Y-m-d');
$clasesEstado = [
    'pendiente' => 'badge-pending',
    'aprobada'  => 'badge-approved',
    'denegada'  => 'badge-denied',
];

$titulo = 'Mis promociones';
require_once __DIR__ . '/../../../Includes/header.php';
require_once __DIR__ . '/../../../Includes/navbar.php';
?>

<main id="contenido-principal" class="container my-4">
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
                        <th scope="col">Destino</th>
                        <th scope="col">Vigencia</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($promociones as $promocion): ?>
                        <?php
                        $vencida = $promocion['fechaHasta'] !== null && $promocion['fechaHasta'] < $hoy;
                        $proxima = $promocion['fechaDesde'] !== null && $promocion['fechaDesde'] > $hoy;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($promocion['descripcion']) ?></td>
                            <td><?= htmlspecialchars($promocion['descuento']) ?>%</td>
                            <td><?= htmlspecialchars($promocion['destino'] ?? 'Todos') ?></td>
                            <td>
                                <?= htmlspecialchars(textoVigencia($promocion['fechaDesde'], $promocion['fechaHasta'])) ?>
                                <?php if ($vencida): ?>
                                    <span class="volara-badge badge-cancelled ms-1">Vencida</span>
                                <?php elseif ($proxima): ?>
                                    <span class="volara-badge badge-neutral ms-1">Próxima</span>
                                <?php endif; ?>
                            </td>
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

        <div class="volara-pagination-container">
            <nav class="volara-pagination" aria-label="Paginación de promociones">
                <ul class="pagination">
                    <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?pagina=<?= max(1, $pagina - 1) ?>"
                           <?= $pagina <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Anterior</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                        <li class="page-item <?= $i === $pagina ? 'active' : '' ?>"
                            <?= $i === $pagina ? 'aria-current="page"' : '' ?>>
                            <a class="page-link" href="?pagina=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>">
                        <a class="page-link" href="?pagina=<?= min($totalPaginas, $pagina + 1) ?>"
                           <?= $pagina >= $totalPaginas ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Siguiente</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../../../Includes/footer.php'; ?>