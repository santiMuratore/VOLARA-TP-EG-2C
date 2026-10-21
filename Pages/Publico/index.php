<?php
require_once __DIR__ . '/../../Includes/functions.php';
require_once __DIR__ . '/../../Includes/functionsF2.php';
require __DIR__ . '/../../Includes/header.php';

$titulo = 'Volara - Inicio';
?>

<div class="container mt-4">
    <div class="row g-4">
        <?php
        $conexion = conectarDB();
        $sql = "SELECT codNovedad, titulo, texto, fechaPublicacion, fechaExpiracion FROM novedades 
                WHERE fechaPublicacion <= CURDATE() AND fechaExpiracion >= CURDATE() 
                ORDER BY fechaPublicacion DESC LIMIT 3";
        $stmt = $conexion->query($sql);
        $novedadesDestacadas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <?php if (count($novedadesDestacadas) > 0): ?>
            <?php foreach ($novedadesDestacadas as $n): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="volara-card news-card h-100">
                        <div class="news-card-body">
                            <h3 class="news-card-title"><?= htmlspecialchars($n['titulo']) ?></h3>
                            <p class="news-card-text"><?= htmlspecialchars(substr($n['texto'], 0, 80)) . '...' ?></p>
                        </div>
                        <div class="news-card-date">
                            <small>
                                <i class="bi bi-calendar-meeting me-1"></i>
                                <?= htmlspecialchars($n['fechaPublicacion']) ?> - Vence: <?= htmlspecialchars($n['fechaExpiracion']) ?>
                            </small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="volara-card flat">
                    <h3 class="text-center">No hay novedades vigentes</h3>
                    <p class="text-center">No hay novedades en este momento.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Sección de Enlaces -->
<div class="container mt-5">
    <div class="row text-center g-4">
        <div class="col-12 col-md-4">
            <div class="volara-card p-4">
                <div class="d-flex flex-column align-items-center">
                    <i class="bi bi-aircraft fs-1 text-primary mb-3"></i>
                    <h3>Aerolíneas</h3>
                    <p>Consulta todas las aerolíneas disponibles</p>
                    <a href="<?= BASE_URL ?>/Pages/Publico/aerolineas.php" class="btn btn-volara mt-2 w-100">
                        <i class="bi bi-arrow-down-circle me-2"></i> Ver aerolíneas
                    </a>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="volara-card p-4">
                <div class="d-flex flex-column align-items-center">
                    <i class="bi bi-map fs-1 text-info mb-3"></i>
                    <a href="<?= BASE_URL ?>/Pages/Publico/vuelos.php" class="btn btn-volara mt-2 w-100">
                        <i class="bi bi-arrow-down-circle me-2"></i> Ver vuelos
                    </a>
                    <p>Busca y consulta todos los vuelos disponibles</p>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="volara-card p-4">
                <div class="d-flex justify-content-center align-items-center">
                    <a href="<?= BASE_URL ?>/Pages/Publico/mapa-sitio.php" class="btn btn-volara mt-2 w-100">
                        <i class="bi bi-box-seam me-2"></i> Mapa de Sitio
                    </a>
                </div>
                <p>Ver toda la estructura del sitio</p>
            </div>
        </div>
    </div>
</div>
</div>
</main>

<?php require __DIR__ . '/../../Includes/footer.php'; ?>
</body>
</html>