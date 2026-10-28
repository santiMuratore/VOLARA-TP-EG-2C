
<?php

http_response_code(404);

require_once __DIR__ . '/../../Includes/functions.php';
require_once __DIR__ . '/../../Includes/functionsF2.php';

$baseUrl = rtrim(BASE_URL, '/');

$titulo = 'Página no encontrada';
$pageDescription = 'La página solicitada no existe en VOLARA.';

require_once __DIR__ . '/../../Includes/header.php';
require_once __DIR__ . '/../../Includes/navbar.php';

?>

<main id="contenido-principal" tabindex="-1">

    <!-- Encabezado -->
    <section class="page-header">

        <div class="container">

            <h1>¡Ups! No encontramos esa página</h1>

            <p class="text-muted mb-3">
                La página que buscás no existe o ya no está disponible.
            </p>

            <nav aria-label="Ruta de navegación">

                <ol class="breadcrumb mb-0">

                    <li class="breadcrumb-item">
                        <a href="<?= htmlspecialchars(
                            $baseUrl . '/Pages/Publico/index.php',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>">
                            Inicio
                        </a>
                    </li>

                    <li
                        class="breadcrumb-item active"
                        aria-current="page"
                    >
                        Error 404
                    </li>

                </ol>

            </nav>

        </div>

    </section>

    <!-- Contenido del error -->
    <section class="section section-gray">

        <div class="container">

            <div class="volara-card-flat text-center py-5">

                <div class="empty-state-icon mb-3">
                    <i
                        class="bi bi-signpost-split"
                        aria-hidden="true"
                    ></i>
                </div>

                <p class="eyebrow">ERROR 404</p>

                <h2 class="h3 mt-2">
                    Este destino no existe
                </h2>

                <p class="text-muted mx-auto mb-4"
                   style="max-width: 540px;">
                    Es posible que el enlace esté mal escrito,
                    que la dirección haya cambiado o que la página
                    ya no esté disponible.
                </p>

                <div class="d-flex flex-wrap justify-content-center gap-2">

                    <a
                        href="<?= htmlspecialchars(
                            $baseUrl . '/Pages/Publico/index.php',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        class="btn btn-volara"
                    >
                        <i
                            class="bi bi-house-door me-2"
                            aria-hidden="true"
                        ></i>
                        Volver al inicio
                    </a>

                    <a
                        href="<?= htmlspecialchars(
                            $baseUrl . '/Pages/Publico/vuelos.php',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        class="btn btn-volara-outline"
                    >
                        <i
                            class="bi bi-airplane me-2"
                            aria-hidden="true"
                        ></i>
                        Buscar vuelos
                    </a>

                    <a
                        href="<?= htmlspecialchars(
                            $baseUrl . '/Pages/Publico/mapa-sitio.php',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        class="btn btn-volara-outline"
                    >
                        <i
                            class="bi bi-diagram-3 me-2"
                            aria-hidden="true"
                        ></i>
                        Mapa del sitio
                    </a>

                </div>

            </div>

        </div>

    </section>

</main>

<?php
require_once __DIR__ . '/../../Includes/footer.php';
?>
