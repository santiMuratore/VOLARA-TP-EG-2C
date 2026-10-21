<?php
require_once __DIR__ . '/../Includes/functions.php';

// Control de acceso: solo usuarios logueados
requerirLogin();

$titulo = 'Novedades';
$errores = [];

// Paginación real en SQL
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$porPagina = 10;

// Conectar y consultar novedades vigentes usando CURDATE()
$conexion = conectarDB();
// Usar CURDATE() en lugar de repetir :hoy en SQL
$sql = "SELECT codNovedad, titulo, texto, fechaPublicacion, fechaExpiracion FROM novedades 
        WHERE fechaPublicacion <= CURDATE() AND fechaExpiracion >= CURDATE() 
        ORDER BY fechaPublicacion DESC 
        LIMIT :offset, :porPagina";
$stmt = $conexion->prepare($sql);
$stmt->bindValue(':offset', ($pagina - 1) * $porPagina, PDO::PARAM_INT);
$stmt->bindValue(':porPagina', $porPagina, PDO::PARAM_INT);
$stmt->execute();
$novedades = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular total para paginación (contando todas las novedades vigentes)
$sqlCount = "SELECT COUNT(*) as total FROM novedades WHERE fechaPublicacion <= CURDATE() AND fechaExpiracion >= CURDATE()";
$countStmt = $conexion->query($sqlCount);
$total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$paginas = paginar($total, $porPagina, $pagina);
$offset = $paginas['offset'];

// Generar enlaces de paginación
$enlacesPaginacion = '';
if ($paginas['totalPaginas'] > 1) {
    $enlacesPaginacion .= '<nav aria-label="Navegación de páginas">
        <ul class="volara-pagination justify-content-center">
        ';
    // Enlace Anterior
    if ($pagina > 1) {
        $enlacesPaginacion .= '<li class="page-item"><a class="page-link" href="?pagina=' . ($pagina - 1) . '">Anterior</a></li>';
    }
    // Números de página (mostrar un rango)
    $maxMostrar = 5;
    $inicio = max(1, $pagina - floor($maxMostrar / 2));
    $fin = min($paginas['totalPaginas'], $inicio + $maxMostrar - 1);
    if ($fin - $inicio < $maxMostrar - 1) {
        $inicio = max(1, $fin - $maxMostrar + 1);
    }
    for ($i = $inicio; $i <= $fin; $i++) {
        $active = ($i == $pagina) ? ' class="active"' : '';
        $enlacesPaginacion .= '<li class="page-item' . $active . '"><a class="page-link" href="?pagina=' . $i . '">' . $i . '</a></li>';
    }
    // Enlace Siguiente
    if ($pagina < $paginas['totalPaginas']) {
        $enlacesPaginacion .= '<li class="page-item"><a class="page-link" href="?pagina=' . ($pagina + 1) . '">Siguiente</a></li>';
    }
    $enlacesPaginacion .= '</ul></nav>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo) ?> | Volara</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/Assets/style.css" rel="stylesheet"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/../Includes/header.php'; ?>

<main class="py-5">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h1 class="section-title text-center mb-4"><?= htmlspecialchars($titulo) ?></h1>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <?php if (count($novedades) > 0): ?>
                    <div class="row g-4">
                        <?php foreach ($novedades as $n): ?>
                            <div class="col-lg-4 col-md-6">
                                <div class="volara-card news-card h-100">
                                    <div class="news-card-body">
                                        <h3 class="news-card-title"><?= htmlspecialchars($n['titulo']) ?></h3>
                                        <p class="news-card-text"><?= htmlspecialchars(substr($n['texto'], 0, 100)) . '...' ?></p>
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
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-12 text-center mt-3">
                <?php if (count($novedades) == 0): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        No hay novedades disponibles por el momento
                    </div>
                <?php endif; ?>
            </div>
        </div>
</div>
</main>

<?php require __DIR__ . '/../Includes/footer.php'; ?>
</body>
</html>