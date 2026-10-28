<?php
require_once __DIR__ . '/../../Includes/functions.php';
require_once __DIR__ . '/../../Includes/functionsF2.php';

$titulo = 'Inicio';
$pageDescription = 'Descubrí destinos, consultá aerolíneas y explorá las novedades de VOLARA.';

$novedadesDestacadas = [];
$errorNovedades = false;

try {
    $conexion = conectarDB();

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
        ORDER BY fechaPublicacion DESC
        LIMIT 3
    ";

    $stmt = $conexion->query($sql);
    $novedadesDestacadas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Error al consultar novedades de VOLARA: ' . $e->getMessage());
    $errorNovedades = true;
}

require __DIR__ . '/../../Includes/header.php';
require __DIR__ . '/../../Includes/navbar.php';
?>

<main id="contenido-principal" tabindex="-1">

    <section class="hero" aria-labelledby="hero-title">
        <div class="container">

            <div class="row align-items-center g-4">

                <div class="col-lg-8">

                    <span class="volara-badge badge-promo mb-3">
                        <i class="bi bi-airplane me-2" aria-hidden="true"></i>
                        Tu próximo viaje comienza aquí
                    </span>

                    <h1 id="hero-title" class="hero-title">
                        Descubrí el mundo.
                        <br>
                        <span>Tu próximo destino te espera.</span>
                    </h1>

                    <p class="hero-subtitle">
                        Explorá vuelos, conocé las aerolíneas disponibles
                        y encontrá nuevas oportunidades para viajar.
                        Todo desde un mismo lugar.
                    </p>

                    <div class="d-flex flex-wrap gap-3">

                        <a
                            href="<?= BASE_URL ?>/Pages/Publico/vuelos.php"
                            class="btn btn-volara btn-volara-lg"
                        >
                            <i class="bi bi-search" aria-hidden="true"></i>
                            Explorar vuelos
                        </a>

                        <a
                            href="<?= BASE_URL ?>/Pages/Publico/aerolineas.php"
                            class="btn btn-volara-outline btn-volara-lg"
                        >
                            <i class="bi bi-airplane" aria-hidden="true"></i>
                            Ver aerolíneas
                        </a>

                    </div>

                </div>

                <div class="col-lg-4">

                    <div class="volara-card">
                        <div class="stat-icon stat-icon-red">
                            <i class="bi bi-globe-americas" aria-hidden="true"></i>
                        </div>

                        <h2 class="h4 mb-3">
                            Tu viaje, más simple
                        </h2>

                        <p class="text-muted mb-4">
                            Consultá las opciones disponibles y encontrá
                            la información que necesitás para organizar
                            tu próximo viaje.
                        </p>

                        <a
                            href="<?= BASE_URL ?>/Pages/Publico/mapa-sitio.php"
                            class="text-decoration-none fw-semibold"
                        >
                            Explorá VOLARA
                            <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
                        </a>
                    </div>

                </div>

            </div>
        </div>
    </section>

    <section class="section" aria-labelledby="accesos-title">
        <div class="container">

            <div class="text-center">
                <h2 id="accesos-title" class="section-title">
                    Explorá VOLARA
                </h2>

                <p class="section-subtitle mx-auto">
                    Encontrá rápidamente lo que estás buscando.
                </p>
            </div>

            <div class="row g-4">

                <div class="col-md-4">
                    <article class="volara-card h-100">

                        <div class="stat-icon stat-icon-red">
                            <i class="bi bi-search" aria-hidden="true"></i>
                        </div>

                        <h3 class="h5">Vuelos disponibles</h3>

                        <p class="text-muted">
                            Consultá los vuelos publicados y explorá
                            las opciones de viaje disponibles.
                        </p>

                        <a
                            href="<?= BASE_URL ?>/Pages/Publico/vuelos.php"
                            class="btn btn-volara w-100 mt-3"
                        >
                            Explorar vuelos
                            <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        </a>

                    </article>
                </div>

                <div class="col-md-4">
                    <article class="volara-card h-100">

                        <div class="stat-icon stat-icon-red">
                            <i class="bi bi-airplane" aria-hidden="true"></i>
                        </div>

                        <h3 class="h5">Aerolíneas</h3>

                        <p class="text-muted">
                            Conocé las aerolíneas registradas en
                            la plataforma y consultá su información.
                        </p>

                        <a
                            href="<?= BASE_URL ?>/Pages/Publico/aerolineas.php"
                            class="btn btn-volara w-100 mt-3"
                        >
                            Ver aerolíneas
                            <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        </a>

                    </article>
                </div>

                <!-- Mapa de sitio -->
                <div class="col-md-4">
                    <article class="volara-card h-100">

                        <div class="stat-icon stat-icon-red">
                            <i class="bi bi-diagram-3" aria-hidden="true"></i>
                        </div>

                        <h3 class="h5">Mapa de sitio</h3>

                        <p class="text-muted">
                            Encontrá las secciones principales de VOLARA
                            desde un único lugar.
                        </p>

                        <a
                            href="<?= BASE_URL ?>/Pages/Publico/mapa-sitio.php"
                            class="btn btn-volara w-100 mt-3"
                        >
                            Explorar el sitio
                            <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        </a>

                    </article>
                </div>

            </div>
        </div>
    </section>

    <section
        class="section section-gray"
        aria-labelledby="novedades-title"
    >
        <div class="container">

            <div class="d-flex flex-wrap justify-content-between
                        align-items-center gap-3 mb-4">

                <div>
                    <h2 id="novedades-title" class="section-title">
                        Novedades
                    </h2>

                    <p class="section-subtitle mb-0">
                        Conocé las últimas novedades de VOLARA.
                    </p>
                </div>

                <a
                    href="<?= BASE_URL ?>/Pages/Publico/novedades.php"
                    class="btn btn-volara-outline"
                >
                    Ver novedades
                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </a>

            </div>

            <?php if ($errorNovedades): ?>

                <div class="alert alert-warning" role="status">
                    <i class="bi bi-exclamation-circle me-2" aria-hidden="true"></i>
                    En este momento no se pudieron cargar las novedades.
                    Volvé a intentarlo más tarde.
                </div>

            <?php elseif (!empty($novedadesDestacadas)): ?>

                <div class="row g-4">

                    <?php foreach ($novedadesDestacadas as $n): ?>

                        <?php
                        $tituloNovedad = (string) ($n['titulo'] ?? '');
                        $textoNovedad = (string) ($n['texto'] ?? '');

                        // Recortar el texto sin cortar caracteres UTF-8.
                        if (function_exists('mb_strimwidth')) {
                            $textoCorto = mb_strimwidth(
                                $textoNovedad,
                                0,
                                160,
                                '...',
                                'UTF-8'
                            );
                        } else {
                            $textoCorto = $textoNovedad;
                        }

                        $fechaPublicacion = !empty($n['fechaPublicacion'])
                            ? date('d/m/Y', strtotime($n['fechaPublicacion']))
                            : '';

                        $fechaExpiracion = !empty($n['fechaExpiracion'])
                            ? date('d/m/Y', strtotime($n['fechaExpiracion']))
                            : '';
                        ?>

                        <div class="col-md-6 col-lg-4">

                            <article class="news-card">

                                <div class="news-card-body">

                                    <div class="news-card-date">
                                        <i
                                            class="bi bi-calendar-event me-1"
                                            aria-hidden="true"
                                        ></i>

                                        Publicada el
                                        <?= htmlspecialchars(
                                            $fechaPublicacion,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </div>

                                    <h3 class="h5">
                                        <?= htmlspecialchars(
                                            $tituloNovedad,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </h3>

                                    <p>
                                        <?= htmlspecialchars(
                                            $textoCorto,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </p>

                                </div>

                                <div class="px-4 pb-3">
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

            <?php else: ?>

                <div class="volara-card-flat text-center">
                    <i
                        class="bi bi-newspaper empty-state-icon"
                        aria-hidden="true"
                    ></i>

                    <h3 class="h5">No hay novedades vigentes</h3>

                    <p class="mb-0">
                        Por el momento no hay novedades para mostrar.
                        Volvé pronto para conocer las próximas actualizaciones.
                    </p>
                </div>

            <?php endif; ?>

        </div>
    </section>

    <section class="section" aria-labelledby="beneficios-title">
        <div class="container">

            <div class="text-center">
                <h2 id="beneficios-title" class="section-title">
                    ¿Por qué elegir VOLARA?
                </h2>
            </div>
            <br>
            <div class="row g-4">

                <div class="col-md-4 text-center">

                    <div class="stat-icon stat-icon-red mx-auto mb-3">
                        <i class="bi bi-search" aria-hidden="true"></i>
                    </div>

                    <h3 class="h5">Información centralizada</h3>

                    <p class="text-muted">
                        Accedé desde un mismo sitio a los vuelos
                        y las aerolíneas disponibles.
                    </p>

                </div>

                <div class="col-md-4 text-center">

                    <div class="stat-icon stat-icon-red mx-auto mb-3">
                        <i class="bi bi-compass" aria-hidden="true"></i>
                    </div>

                    <h3 class="h5">Navegación sencilla</h3>

                    <p class="text-muted">
                        Explorá las secciones del sitio y encontrá
                        rápidamente la información que necesitás.
                    </p>

                </div>

                <div class="col-md-4 text-center">

                    <div class="stat-icon stat-icon-red mx-auto mb-3">
                        <i class="bi bi-person-check" aria-hidden="true"></i>
                    </div>

                    <h3 class="h5">Tu cuenta VOLARA</h3>

                    <p class="text-muted">
                        Ingresá a la plataforma para acceder
                        a las opciones disponibles para tu usuario.
                    </p>

                </div>

            </div>

        </div>
    </section>

</main>

<?php require __DIR__ . '/../../Includes/footer.php'; ?>