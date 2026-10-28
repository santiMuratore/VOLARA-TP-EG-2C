<?php
require_once __DIR__ . '/../../Includes/functions.php';
require_once __DIR__ . '/../../Includes/functionsF2.php';

$titulo = 'Centro de ayuda';
$pageDescription = 'Encontrá respuestas a las preguntas frecuentes sobre VOLARA.';

require __DIR__ . '/../../Includes/header.php';
require __DIR__ . '/../../Includes/navbar.php';
?>

<main id="contenido-principal" class="volara-public-page volara-ayuda-page" tabindex="-1">

    <section class="page-header">
        <div class="container">
            <h1>Centro de ayuda</h1>

            <p class="text-muted mb-3">
                Encontrá respuestas a las preguntas más frecuentes.
            </p>

            <nav aria-label="Ruta de navegación">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="<?= BASE_URL ?>/Pages/Publico/index.php">Inicio</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        Ayuda
                    </li>
                </ol>
            </nav>
        </div>
    </section>

    <section class="volara-public-section">
        <div class="container">

            <div class="row g-4">

                <div class="col-12 col-lg-8">

                    <article class="volara-card mb-4">
                        <h2 class="h5 mb-3">
                            <i class="bi bi-search text-danger me-2"></i>
                            ¿Cómo puedo buscar vuelos?
                        </h2>

                        <p class="mb-3">
                            Ingresá en «Buscar vuelos» y completá los filtros
                            de origen, destino, fecha de salida y clase.
                        </p>

                        <a
                            href="<?= BASE_URL ?>/Pages/Publico/vuelos.php"
                            class="btn btn-volara-outline btn-volara-sm"
                        >
                            Buscar vuelos
                            <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </article>

                    <article class="volara-card mb-4">
                        <h2 class="h5 mb-3">
                            <i class="bi bi-airplane text-danger me-2"></i>
                            ¿Dónde consulto las aerolíneas?
                        </h2>

                        <p class="mb-0">
                            En la sección «Aerolíneas» podés consultar las
                            compañías registradas en VOLARA y conocer los
                            datos publicados de cada una.
                        </p>

                        <a
                            href="<?= BASE_URL ?>/Pages/Publico/aerolineas.php"
                            class="btn btn-volara-outline btn-volara-sm mt-3"
                        >
                            Ver aerolíneas
                            <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </article>

                    <article class="volara-card mb-4">
                        <h2 class="h5 mb-3">
                            <i class="bi bi-tag text-danger me-2"></i>
                            ¿Dónde encuentro las promociones?
                        </h2>

                        <p class="mb-3">
                            Entrá en «Descubrí» y seleccioná
                            «Promociones» para consultar las ofertas publicadas.
                        </p>

                        <a
                            href="<?= BASE_URL ?>/Pages/Publico/promociones.php"
                            class="btn btn-volara-outline btn-volara-sm"
                        >
                            Ver promociones
                            <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </article>

                    <article class="volara-card">
                        <h2 class="h5 mb-3">
                            <i class="bi bi-person-circle text-danger me-2"></i>
                            ¿Necesito iniciar sesión?
                        </h2>

                        <p class="mb-0">
                            Algunas funciones personales requieren una
                            cuenta y una sesión iniciada. Las opciones
                            disponibles dependen de tu tipo de usuario.
                        </p>
                    </article>

                </div>

                <aside class="col-12 col-lg-4">
                    <div class="volara-card">

                        <div class="stat-icon stat-icon-red mb-3">
                            <i class="bi bi-headset"></i>
                        </div>

                        <h2 class="h5">¿Necesitás más ayuda?</h2>

                        <p class="text-muted">
                            Si no encontraste la respuesta que buscabas,
                            escribinos y contanos tu consulta.
                        </p>

                        <a
                            href="<?= BASE_URL ?>/Pages/Publico/contacto.php"
                            class="btn btn-volara w-100"
                        >
                            <i class="bi bi-envelope me-2"></i>
                            Contactanos
                        </a>

                        <a
                            href="<?= BASE_URL ?>/Pages/Publico/mapa-sitio.php"
                            class="btn btn-volara-outline w-100 mt-2"
                        >
                            Mapa del sitio
                        </a>

                    </div>
                </aside>

            </div>

        </div>
    </section>

</main>

<?php require __DIR__ . '/../../Includes/footer.php'; ?>
