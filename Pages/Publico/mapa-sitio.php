<?php
require_once __DIR__ . '/../../Includes/functions.php';
require_once __DIR__ . '/../../Includes/functionsF2.php';

// Título utilizado por el encabezado compartido.
$titulo = 'Mapa de sitio';

// Obtiene los enlaces públicos.
$rutas = obtenerRutasSitio();

// Incluye el encabezado común de Volara.
require __DIR__ . '/../../Includes/header.php';
?>

<main class="py-5">
    <div class="container">

        <!-- Encabezado de la página -->
        <div class="text-center mb-5">
            <span class="badge text-bg-danger mb-3">
                <i class="bi bi-diagram-3 me-1"></i>
                Navegación
            </span>

            <h1 class="section-title">Mapa de sitio</h1>

            <p class="section-subtitle">
                Explorá las principales secciones de Volara
                y accedé directamente a cada una.
            </p>
        </div>

        <!-- Listado de secciones -->
        <div class="row justify-content-center">
            <div class="col-lg-9">

                <div class="volara-card">
                    <div class="p-4 p-md-5">

                        <h2 class="h4 mb-4">
                            <i class="bi bi-signpost-2 me-2 text-danger"></i>
                            Secciones disponibles
                        </h2>

                        <?php if (!empty($rutas)): ?>

                            <div class="list-group list-group-flush">

                                <?php foreach ($rutas as $ruta): ?>

                                    <a
                                        href="<?= htmlspecialchars(
                                            $ruta['url'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        class="list-group-item list-group-item-action
                                               d-flex justify-content-between
                                               align-items-center py-3"
                                    >

                                        <span>
                                            <i class="bi bi-link-45deg
                                                      text-danger me-2"></i>

                                            <?= htmlspecialchars(
                                                $ruta['enlace'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </span>

                                        <i class="bi bi-arrow-up-right"></i>

                                    </a>

                                <?php endforeach; ?>

                            </div>

                        <?php else: ?>

                            <div class="alert alert-info mb-0" role="status">
                                No hay secciones disponibles para mostrar.
                            </div>

                        <?php endif; ?>

                    </div>
                </div>

                <!-- Información adicional -->
                <div class="text-center mt-4">
                    <p class="text-muted small">
                        Volara · Sistema de reservas de vuelos
                    </p>

                    <a
                        href="<?= BASE_URL ?>/Pages/Publico/index.php"
                        class="btn btn-volara"
                    >
                        <i class="bi bi-house-door me-2"></i>
                        Volver al inicio
                    </a>
                </div>

            </div>
        </div>

    </div>
</main>

<?php require __DIR__ . '/../../Includes/footer.php'; ?>

</body>
</html>