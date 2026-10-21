<?php
require_once __DIR__ . '/../../Includes/functions.php';
require_once __DIR__ . '/../../Includes/functionsF2.php';
require __DIR__ . '/../../Includes/header.php';

$titulo = 'Vuelos - Volara';
?>

<!-- Listado de Vuelos -->
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="section-title">Vuelos</h1>
    </div>

    <?php
    $pagina = max(1, (int)($_GET['pagina'] ?? 1));
    $porPagina = 10;

    $conexion = conectarDB();

    // Contar total
    $sqlCount = "SELECT COUNT(*) as total FROM vuelos";
    $countStmt = $conexion->query($sqlCount);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Paginación
    $paginacion = paginar($total, $porPagina, $pagina);
    $offset = $paginacion['offset'];

    // Consultar vuelos con JOIN a aerolíneas y bindValue
    $sql = "SELECT v.codigoVuelo, v.origen, v.destino, v.fecha, v.hora, a.nombre as nombreAerolinea, v.precio, v.clase 
            FROM vuelos v INNER JOIN aerolineas a ON v.codAerolinea = a.codAerolinea 
            ORDER BY v.fecha DESC, v.hora DESC LIMIT :offset, :porPagina";
    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':porPagina', $porPagina, PDO::PARAM_INT);
    $stmt->execute();
    $vuelos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <?php if (count($vuelos) > 0): ?>
        <div class="table-responsive">
            <table class="volara-table">
                <thead>
                    <tr>
                        <th>Vuelo</th>
                        <th>Origen</th>
                        <th>Destino</th>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Aerolínea</th>
                        <th>Precio</th>
                        <th>Clase</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vuelos as $v): ?>
                        <tr>
                            <td><?= htmlspecialchars($v['codigoVuelo']) ?></td>
                            <td><?= htmlspecialchars($v['origen']) ?></td>
                            <td><?= htmlspecialchars($v['destino']) ?></td>
                            <td><?= htmlspecialchars($v['fecha']) ?></td>
                            <td><?= htmlspecialchars($v['hora']) ?></td>
                            <td><?= htmlspecialchars($v['nombreAerolinea']) ?></td>
                            <td><?= '$' . number_format($v['precio'], 0, ',', '.') ?></td>
                            <td><span class="badge bg-<?= $v['clase'] === 'business' ? 'dark' : ($v['clase'] === 'premium' ? 'secondary' : 'primary') ?>"><?= htmlspecialchars($v['clase']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state text-center py-5">
            <i class="bi bi-airplane me-2" style="font-size: 3rem;"></i>
            <h3>No hay vuelos disponibles</h3>
            <p>No hay vuelos programados en este momento.</p>
        </div>
    <?php endif; ?>

    <!-- Información sobre reservas -->
    <div class="alert alert-info mt-3" role="alert">
        <i class="bi bi-info-circle me-2"></i>
        <strong>Nota:</strong> Para realizar una reserva, debe iniciar sesión primero.
    </div>
    <?php if (!isset($_SESSION['codUsuario'])): ?>
        <div class="mt-3 text-center">
            <a href="<?= BASE_URL ?>/Auth/login.php" class="btn btn-volara">
                <i class="bi bi-door-open me-2"></i> Iniciar sesión para reservar
            </a>
        </div>
    <?php endif; ?>
    </div>
</div>