<?php
require_once __DIR__ . '/../../Includes/functions.php';
require_once __DIR__ . '/../../Includes/functionsF2.php';

// Configuración de la página.
$titulo = 'Promociones';
$pageDescription = 'Descubrí las promociones y descuentos de las aerolíneas de VOLARA.';

// Variables iniciales.
$promociones = [];
$total = 0;
$error = null;

$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = 9;

// Estados que pueden mostrarse públicamente.
// Ajustar esta lista si tu base de datos utiliza otros valores.
$estadosPublicos = "'aprobada', 'aprobado', 'vigente', 'activa', 'publicada', 'publicado'";

try {
    $conexion = conectarDB();

    // Contar las promociones publicables.
    $sqlCount = "
        SELECT COUNT(*)
        FROM promociones p
        INNER JOIN aerolineas a
            ON p.codAerolinea = a.codAerolinea
        WHERE LOWER(TRIM(p.estado)) IN ($estadosPublicos)
    ";

    $total = (int) $conexion->query($sqlCount)->fetchColumn();

    // Calcular la paginación.
    $paginacion = paginar($total, $porPagina, $pagina);
    $pagina = $paginacion['paginaActual'];
    $offset = $paginacion['offset'];

    // Consultar promociones y aerolíneas asociadas.
    $sql = "
        SELECT
            p.codPromocion,
            p.descripcion,
            p.descuento,
            p.estado,
            a.nombre AS nombreAerolinea,
            a.codigoIATA AS codigoIATA
        FROM promociones p
        INNER JOIN aerolineas a
            ON p.codAerolinea = a.codAerolinea
        WHERE LOWER(TRIM(p.estado)) IN ($estadosPublicos)
        ORDER BY p.descuento DESC, p.codPromocion DESC
        LIMIT :limite OFFSET :offset
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $promociones = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Error al consultar promociones: ' . $e->getMessage());
    $error = 'No se pudieron cargar las promociones. Intentá nuevamente más tarde.';
}

// Encabezado y navegación compartidos.
require __DIR__ . '/../../Includes/header.php';
require __DIR__ . '/../../Includes/navbar.php';
?>

<main id="contenido-principal" tabindex="-1">

    <!-- Encabezado -->
    <section class="page-header">
        <div class="container">

            <h1>Promociones</h1>

            <p class="text-muted mb-3">
                Descubrí descuentos y ofertas de las aerolíneas de VOLARA.
            </p>

            <nav aria-label="Ruta de navegación">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="<?= BASE_URL ?>/Pages/Publico/index.php">
                            Inicio
                        </a>
                    </li>

                    <li class="breadcrumb-item active" aria-current="page">
                        Promociones
                    </li>
                </ol>
            </nav>

        </div>
    </section>

    <!-- Listado de promociones -->
    <section class="section">
        <div class="container">

            <div class="d-flex flex-wrap justify-content-between
                        align-items-center gap-3 mb-4">

                <div>
                    <h2 class="section-title">Ofertas disponibles</h2>

                    <p class="text-muted mb-0">
                        Explorá las promociones publicadas por las aerolíneas.
                    </p>
                </div>

                <?php if ($error === null): ?>
                    <span class="volara-badge badge-promo">
                        <i class="bi bi-tag me-1" aria-hidden="true"></i>
                        <?= $total ?>
                        <?= $total === 1 ? 'promoción' : 'promociones' ?>
                    </span>
                <?php endif; ?>

            </div>

            <?php if ($error !== null): ?>

                <div class="volara-alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>

            <?php elseif (empty($promociones)): ?>

                <div class="volara-card-flat text-center">

                    <i
                        class="bi bi-tags empty-state-icon"
                        aria-hidden="true"
                    ></i>

                    <h3 class="h4">No hay promociones vigentes</h3>

                    <p class="text-muted">
                        Cuando haya nuevas promociones aprobadas y publicadas,
                        podrás consultarlas desde esta sección.
                    </p>

                    <a
                        href="<?= BASE_URL ?>/Pages/Publico/vuelos.php"
                        class="btn btn-volara mt-3"
                    >
                        <i class="bi bi-search me-2" aria-hidden="true"></i>
                        Consultar vuelos
                    </a>

                </div>

            <?php else: ?>

                <div class="row g-4">

                    <?php foreach ($promociones as $promo): ?>

                        <div class="col-md-6 col-lg-4">

                            <article class="volara-card h-100 d-flex flex-column">

                                <!-- Descuento -->
                                <div class="d-flex justify-content-between
                                            align-items-center gap-2 mb-4">

                                    <div class="stat-icon stat-icon-red mb-0">
                                        <i
                                            class="bi bi-tag"
                                            aria-hidden="true"
                                        ></i>
                                    </div>

                                    <span class="volara-badge badge-promo">
                                        <?= number_format(
                                            (float) $promo['descuento'],
                                            0,
                                            ',',
                                            '.'
                                        ) ?>% OFF
                                    </span>

                                </div>

                                <!-- Aerolínea -->
                                <p class="eyebrow mb-2">
                                    <?= htmlspecialchars(
                                        $promo['nombreAerolinea'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                    <?php if (!empty($promo['codigoIATA'])): ?>
                                        ·
                                        <?= htmlspecialchars(
                                            $promo['codigoIATA'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    <?php endif; ?>
                                </p>

                                <!-- Descripción -->
                                <h3 class="h5 mb-3">
                                    Oferta de
                                    <?= htmlspecialchars(
                                        $promo['nombreAerolinea'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </h3>

                                <p class="text-muted mb-4">
                                    <?= nl2br(htmlspecialchars(
                                        $promo['descripcion'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    )) ?>
                                </p>

                                <!-- Acción -->
                                <div class="mt-auto">

                                    <a
                                        href="<?= BASE_URL ?>/Pages/Publico/vuelos.php"
                                        class="btn btn-volara w-100"
                                    >
                                        Consultar vuelos
                                        <i
                                            class="bi bi-arrow-right"
                                            aria-hidden="true"
                                        ></i>
                                    </a>

                                </div>

                            </article>

                        </div>

                    <?php endforeach; ?>

                </div>

                <!-- Paginación -->
                <?php if ($paginacion['totalPaginas'] > 1): ?>

                    <div class="mt-5">
                        <?= generarPaginacionHTML(
                            $total,
                            $porPagina,
                            $pagina,
                            BASE_URL . '/Pages/Publico/promociones.php'
                        ) ?>
                    </div>

                <?php endif; ?>

            <?php endif; ?>

        </div>
    </section>

</main>

<?php require __DIR__ . '/../../Includes/footer.php'; ?>