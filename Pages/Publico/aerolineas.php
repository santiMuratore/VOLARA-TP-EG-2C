<?php
require_once __DIR__ . '/../../Includes/functions.php';
require_once __DIR__ . '/../../Includes/functionsF2.php';

$titulo = 'Aerolíneas';
$pageDescription = 'Conocé las aerolíneas registradas en VOLARA.';

$aerolineas = [];
$error = null;
$total = 0;

$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = 9;

try {
    $conexion = conectarDB();

    $stmt = $conexion->query(
        "SELECT COUNT(*) AS total FROM aerolineas"
    );

    $total = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $paginacion = paginar($total, $porPagina, $pagina);
    $pagina = $paginacion['paginaActual'];
    $offset = $paginacion['offset'];

    $sql = "
        SELECT codAerolinea, codigoIATA, nombre, pais
        FROM aerolineas
        ORDER BY nombre ASC
        LIMIT :porPagina OFFSET :offset
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':porPagina', $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $aerolineas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Error al consultar aerolíneas: ' . $e->getMessage());
    $error = 'No se pudieron cargar las aerolíneas. Intentá nuevamente más tarde.';
}

require __DIR__ . '/../../Includes/header.php';
require __DIR__ . '/../../Includes/navbar.php';
?>

<main id="contenido-principal" tabindex="-1">

    <!-- Encabezado -->
    <section class="page-header">
        <div class="container">

            <h1>Aerolíneas</h1>

            <p class="text-muted mb-3">
                Conocé las compañías registradas en nuestra plataforma.
            </p>

            <nav aria-label="Ruta de navegación">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="<?= BASE_URL ?>/Pages/Publico/index.php">
                            Inicio
                        </a>
                    </li>

                    <li class="breadcrumb-item active" aria-current="page">
                        Aerolíneas
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
                    <h2 class="section-title">Nuestras aerolíneas</h2>
                    <p class="text-muted mb-0">
                        Consultá la información de cada compañía.
                    </p>
                </div>

                <?php if ($error === null): ?>
                    <span class="volara-badge badge-neutral">
                        <?= $total ?>
                        <?= $total === 1 ? 'aerolínea' : 'aerolíneas' ?>
                    </span>
                <?php endif; ?>

            </div>

            <?php if ($error !== null): ?>

                <div class="volara-alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>

            <?php elseif (empty($aerolineas)): ?>

                <div class="volara-card-flat text-center">
                    <i
                        class="bi bi-airplane empty-state-icon"
                        aria-hidden="true"
                    ></i>

                    <h3 class="h4">Todavía no hay aerolíneas</h3>

                    <p class="text-muted mb-0">
                        En este momento no hay aerolíneas registradas
                        para mostrar.
                    </p>

                    <a
                        href="<?= BASE_URL ?>/Pages/Publico/index.php"
                        class="btn btn-volara mt-4"
                    >
                        <i class="bi bi-house-door me-2" aria-hidden="true"></i>
                        Volver al inicio
                    </a>
                </div>

            <?php else: ?>

                <div class="row g-4">

                    <?php foreach ($aerolineas as $a): ?>

                        <div class="col-md-6 col-lg-4">

                            <article class="volara-card h-100 d-flex flex-column">

                                <div class="d-flex justify-content-between
                                            align-items-center mb-4">

                                    <div class="stat-icon stat-icon-red mb-0">
                                        <i
                                            class="bi bi-airplane"
                                            aria-hidden="true"
                                        ></i>
                                    </div>

                                    <span class="volara-badge badge-neutral">
                                        <?= htmlspecialchars(
                                            $a['codigoIATA'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </span>

                                </div>

                                <h3 class="h5 mb-3">
                                    <?= htmlspecialchars(
                                        $a['nombre'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </h3>

                                <p class="text-muted mb-4">
                                    <i
                                        class="bi bi-geo-alt me-2"
                                        aria-hidden="true"
                                    ></i>

                                    <?= htmlspecialchars(
                                        $a['pais'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </p>

                                <div class="mt-auto">
                                    <a
                                        href="<?= BASE_URL ?>/Pages/Publico/vuelos.php"
                                        class="btn btn-volara-outline w-100"
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

                <?php if ($total > $porPagina): ?>
                    <div class="mt-5">
                        <?= generarPaginacionHTML(
                            $total,
                            $porPagina,
                            $pagina,
                            BASE_URL . '/Pages/Publico/aerolineas.php'
                        ) ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

        </div>
    </section>

</main>

<?php require __DIR__ . '/../../Includes/footer.php'; ?>