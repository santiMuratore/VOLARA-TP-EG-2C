<?php
require_once __DIR__ . '/../../Includes/functions.php';
require_once __DIR__ . '/../../Includes/functionsF2.php';

$titulo = 'Novedades';
$pageDescription = 'Conocé las últimas novedades y comunicaciones de VOLARA.';

$novedades = [];
$total = 0;
$error = null;

$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = 9;

try {
    $conexion = conectarDB();

    $sqlCount = "
        SELECT COUNT(*) AS total
        FROM novedades
        WHERE fechaPublicacion <= CURDATE()
          AND fechaExpiracion >= CURDATE()
    ";

    $countStmt = $conexion->query($sqlCount);
    $total = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    $paginacion = paginar($total, $porPagina, $pagina);
    $pagina = $paginacion['paginaActual'];
    $offset = $paginacion['offset'];

    $sql = "
        SELECT
            codNovedad,
            titulo,
            texto,
            fechaPublicacion,
            fechaExpiracion
        FROM novedades
        WHERE fechaPublicacion <= CURDATE()
          AND fechaExpiracion >= CURDATE()
        ORDER BY fechaPublicacion DESC, codNovedad DESC
        LIMIT :limite OFFSET :offset
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $novedades = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Error al consultar novedades de VOLARA: ' . $e->getMessage());
    $error = 'No se pudieron cargar las novedades. Intentá nuevamente más tarde.';
}

$totalPaginas = $paginacion['totalPaginas'] ?? 1;
$inicioPagina = max(1, $pagina - 2);
$finPagina = min($totalPaginas, $inicioPagina + 4);

if ($finPagina - $inicioPagina < 4) {
    $inicioPagina = max(1, $finPagina - 4);
}

$urlNovedades = BASE_URL . '/Pages/Publico/novedades.php';

require __DIR__ . '/../../Includes/header.php';
require __DIR__ . '/../../Includes/navbar.php';
?>

<main id="contenido-principal" tabindex="-1">

    <section class="page-header">
        <div class="container">

            <h1>Novedades</h1>

            <p class="text-muted mb-3">
                Enterate de las últimas novedades y comunicaciones de VOLARA.
            </p>

            <nav aria-label="Ruta de navegación">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="<?= BASE_URL ?>/Pages/Publico/index.php">
                            Inicio
                        </a>
                    </li>

                    <li class="breadcrumb-item active" aria-current="page">
                        Novedades
                    </li>
                </ol>
            </nav>

        </div>
    </section>

    <section class="section">
        <div class="container">

            <div class="d-flex flex-wrap justify-content-between
                        align-items-center gap-3 mb-4">

                <div>
                    <h2 class="section-title">Últimas novedades</h2>

                    <p class="text-muted mb-0">
                        Información importante para nuestra comunidad.
                    </p>
                </div>

                <?php if ($error === null): ?>
                    <span class="volara-badge badge-neutral">
                        <?= $total ?>
                        <?= $total === 1 ? 'novedad' : 'novedades' ?>
                    </span>
                <?php endif; ?>

            </div>

            <?php if ($error !== null): ?>

                <div class="volara-alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-circle" aria-hidden="true"></i>

                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>

            <?php elseif (empty($novedades)): ?>

                <div class="volara-card-flat text-center">

                    <i
                        class="bi bi-newspaper empty-state-icon"
                        aria-hidden="true"
                    ></i>

                    <h3 class="h4">No hay novedades publicadas</h3>

                    <p class="text-muted">
                        Por el momento no hay novedades vigentes.
                        Volvé pronto para conocer las próximas actualizaciones.
                    </p>

                    <a
                        href="<?= BASE_URL ?>/Pages/Publico/index.php"
                        class="btn btn-volara mt-3"
                    >
                        <i class="bi bi-house-door me-2" aria-hidden="true"></i>
                        Volver al inicio
                    </a>

                </div>

            <?php else: ?>

                <div class="row g-4">

                    <?php foreach ($novedades as $n): ?>

                        <?php
                        $textoNovedad = (string) ($n['texto'] ?? '');

                        if (function_exists('mb_strimwidth')) {
                            $resumen = mb_strimwidth(
                                $textoNovedad,
                                0,
                                180,
                                '...',
                                'UTF-8'
                            );
                        } else {
                            $resumen = strlen($textoNovedad) > 180
                                ? substr($textoNovedad, 0, 177) . '...'
                                : $textoNovedad;
                        }

                        $fechaPublicacion = !empty($n['fechaPublicacion'])
                            && strtotime($n['fechaPublicacion']) !== false
                            ? date('d/m/Y', strtotime($n['fechaPublicacion']))
                            : '';

                        $fechaExpiracion = !empty($n['fechaExpiracion'])
                            && strtotime($n['fechaExpiracion']) !== false
                            ? date('d/m/Y', strtotime($n['fechaExpiracion']))
                            : '';
                        ?>

                        <div class="col-md-6 col-lg-4">

                            <article class="news-card h-100">

                                <div class="news-card-body">

                                    <div class="news-card-date">
                                        <i
                                            class="bi bi-calendar-event me-1"
                                            aria-hidden="true"
                                        ></i>

                                        <?= htmlspecialchars(
                                            $fechaPublicacion,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </div>

                                    <h3 class="h5">
                                        <?= htmlspecialchars(
                                            $n['titulo'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </h3>

                                    <p>
                                        <?= htmlspecialchars(
                                            $resumen,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </p>

                                </div>

                                <div class="px-4 pb-3 mt-auto">
                                    <?php if ($fechaExpiracion !== ''): ?>
                                        <small class="text-muted">
                                            <i
                                                class="bi bi-clock me-1"
                                                aria-hidden="true"
                                            ></i>

                                            Vigente hasta
                                            <?= htmlspecialchars(
                                                $fechaExpiracion,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </small>
                                    <?php endif; ?>
                                </div>

                            </article>

                        </div>

                    <?php endforeach; ?>

                </div>

                <?php if ($totalPaginas > 1): ?>

                    <nav
                        class="volara-pagination"
                        aria-label="Paginación de novedades"
                    >

                        <?php if ($pagina > 1): ?>
                            <a
                                href="<?= $urlNovedades ?>?pagina=<?= $pagina - 1 ?>"
                                aria-label="Página anterior"
                            >
                                <i class="bi bi-chevron-left" aria-hidden="true"></i>
                            </a>
                        <?php endif; ?>

                        <?php for ($i = $inicioPagina; $i <= $finPagina; $i++): ?>

                            <?php if ($i === $pagina): ?>
                                <span class="active" aria-current="page">
                                    <?= $i ?>
                                </span>
                            <?php else: ?>
                                <a href="<?= $urlNovedades ?>?pagina=<?= $i ?>">
                                    <?= $i ?>
                                </a>
                            <?php endif; ?>

                        <?php endfor; ?>

                        <?php if ($pagina < $totalPaginas): ?>
                            <a
                                href="<?= $urlNovedades ?>?pagina=<?= $pagina + 1 ?>"
                                aria-label="Página siguiente"
                            >
                                <i class="bi bi-chevron-right" aria-hidden="true"></i>
                            </a>
                        <?php endif; ?>

                    </nav>

                <?php endif; ?>

            <?php endif; ?>

        </div>
    </section>

</main>

<?php require __DIR__ . '/../../Includes/footer.php'; ?>