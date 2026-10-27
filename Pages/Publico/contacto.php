
<?php
require_once __DIR__ . '/../../Includes/functions.php';
require_once __DIR__ . '/../../Includes/functionsF2.php';

$titulo = 'Contacto';
$pageDescription = 'Contactate con el equipo de VOLARA.';

require __DIR__ . '/../../Includes/header.php';
require __DIR__ . '/../../Includes/navbar.php';
?>

<main id="contenido-principal" class="volara-public-page volara-contacto-page" tabindex="-1">

    <!-- Encabezado -->
    <section class="page-header">
        <div class="container">
            <h1>Contacto</h1>

            <p class="text-muted mb-3">
                Estamos disponibles para recibir tus consultas.
            </p>

            <nav aria-label="Ruta de navegación">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="<?= BASE_URL ?>/Pages/Publico/index.php">Inicio</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        Contacto
                    </li>
                </ol>
            </nav>
        </div>
    </section>

    <!-- Información de contacto -->
    <section class="volara-public-section">
        <div class="container">

            <div class="row justify-content-center g-4">

                <!-- Datos de contacto -->
                <div class="col-12 col-lg-7">
                    <div class="volara-card">

                        <div class="stat-icon stat-icon-red mb-3">
                            <i class="bi bi-chat-dots"></i>
                        </div>

                        <h2 class="h4 mb-3">
                            ¿En qué podemos ayudarte?
                        </h2>

                        <p class="text-muted">
                            Podés escribirnos para realizar consultas
                            sobre la plataforma, informar un inconveniente
                            o solicitar orientación sobre sus secciones.
                        </p>

                        <hr class="my-4">

                        <div class="d-flex align-items-start gap-3 mb-4">
                            <div class="stat-icon stat-icon-red mb-0 flex-shrink-0">
                                <i class="bi bi-envelope"></i>
                            </div>

                            <div class="min-w-0">
                                <h3 class="h6 mb-1">Correo electrónico</h3>

                                <a
                                    href="mailto:volaraairline@gmail.com"
                                    class="text-break"
                                >
                                    volaraairline@gmail.com
                                </a>

                                <p class="text-muted small mt-1 mb-0">
                                    Seleccioná el correo para escribirnos
                                    desde tu aplicación de email.
                                </p>
                            </div>
                        </div>

                        <div class="d-flex align-items-start gap-3 mb-4">
                            <div class="stat-icon stat-icon-red mb-0 flex-shrink-0">
                                <i class="bi bi-geo-alt"></i>
                            </div>

                            <div>
                                <h3 class="h6 mb-1">Ubicación académica</h3>

                                <p class="text-muted mb-0">
                                    Rosario, Santa Fe, Argentina
                                    <br>
                                    UTN Facultad Regional Rosario
                                </p>
                            </div>
                        </div>

                        <a
                            href="mailto:volara.aerolinea@gmail.com?subject=Consulta%20sobre%20VOLARA"
                            class="btn btn-volara"
                        >
                            <i class="bi bi-send me-2"></i>
                            Escribir un correo
                        </a>

                    </div>
                </div>

                <!-- Enlaces complementarios -->
                <aside class="col-12 col-lg-5">

                    <div class="volara-card mb-4">
                        <h2 class="h5 mb-3">
                            <i class="bi bi-question-circle text-danger me-2"></i>
                            ¿Buscás una respuesta rápida?
                        </h2>

                        <p class="text-muted">
                            Revisá las preguntas frecuentes antes de
                            enviarnos tu consulta.
                        </p>

                        <a
                            href="<?= BASE_URL ?>/Pages/Publico/ayuda.php"
                            class="btn btn-volara-outline w-100"
                        >
                            Ir al centro de ayuda
                            <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>

                    <div class="volara-card">
                        <h2 class="h5 mb-3">
                            <i class="bi bi-compass text-danger me-2"></i>
                            Explorá VOLARA
                        </h2>

                        <p class="text-muted">
                            También podés consultar los vuelos, las
                            aerolíneas y las novedades publicadas.
                        </p>

                        <a
                            href="<?= BASE_URL ?>/Pages/Publico/vuelos.php"
                            class="btn btn-volara w-100"
                        >
                            Buscar vuelos
                        </a>
                    </div>

                </aside>

            </div>

        </div>
    </section>

</main>

<?php require __DIR__ . '/../../Includes/footer.php'; ?>
