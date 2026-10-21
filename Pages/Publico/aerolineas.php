<?php
require_once __DIR__ . '/../Includes/functions.php';
require __DIR__ . '/../Includes/header.php';

$titulo = 'Aerolíneas - Volara';
?>

<!-- Listado de Aerolíneas -->
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="section-title">Aerolíneas</h1>
    </div>

    <?php
    $pagina = max(1, (int)($_GET['pagina'] ?? 1));
    $porPagina = 10;

    $conexion = conectarDB();

    // Contar total
    $sqlCount = "SELECT COUNT(*) as total FROM aerolineas";
    $countStmt = $conexion->query($sqlCount);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Paginación usando helper de functions.php
    $paginacion = paginar($total, $porPagina, $pagina);
    $offset = $paginacion['offset'];

    // Consultar aerolíneas con bindValue
    $sql = "SELECT codAerolinea, codigoIATA, nombre, pais FROM aerolineas ORDER BY nombre ASC LIMIT :offset, :porPagina";
    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':porPagina', $porPagina, PDO::PARAM_INT);
    $stmt->execute();
    $aerolineas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <?php if (count($aerolineas) > 0): ?>
        <div class="table-responsive">
            <table class="volara-table">
                <thead>
                    <tr>
                        <th>Código IATA</th>
                        <th>Nombre</th>
                        <th>País</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($aerolineas as $a): ?>
                        <tr>
                            <td><?= htmlspecialchars($a['codigoIATA']) ?></td>
                            <td><?= htmlspecialchars($a['nombre']) ?></td>
                            <td><?= htmlspecialchars($a['pais']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state text-center py-5">
            <i class="bi bi-airplane me-2" style="font-size: 3rem;"></i>
            <h3>No hay aerolíneas registradas</h3>
            <p>No hay aerolíneas disponibles en este momento.</p>
        </div>
    <?php endif; ?>

    <!-- Paginación -->
    <?= generarPaginacionHTML($total, $porPagina, $pagina, BASE_URL . '/Pages/Publico/aerolineas.php') ?>
</div>