<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../Includes/functions.php';
require_once __DIR__ . '/../../Includes/functionsF2.php';

/*
|--------------------------------------------------------------------------
| Configuración de la página
|--------------------------------------------------------------------------
*/

$titulo = 'Mapa del sitio';
$pageDescription = 'Explorá las secciones disponibles de VOLARA.';

$baseUrl = rtrim(BASE_URL, '/');
$raizProyecto = dirname(__DIR__, 2);

/*
|--------------------------------------------------------------------------
| Usuario actual
|--------------------------------------------------------------------------
*/

$estaLogueado = !empty($_SESSION['codUsuario']);

$usuario = [];

if ($estaLogueado && function_exists('usuarioActual')) {
    $usuarioActual = usuarioActual();

    if (is_array($usuarioActual)) {
        $usuario = $usuarioActual;
    }
}

/*
|--------------------------------------------------------------------------
| Normalización del rol
|--------------------------------------------------------------------------
*/

$rol = trim((string) (
    $usuario['rol']
    ?? $usuario['tipoUsuario']
    ?? $_SESSION['tipoUsuario']
    ?? ''
));

$rol = function_exists('mb_strtolower')
    ? mb_strtolower($rol, 'UTF-8')
    : strtolower($rol);

$rolNormalizado = strtr($rol, [
    'á' => 'a',
    'é' => 'e',
    'í' => 'i',
    'ó' => 'o',
    'ú' => 'u',
    'ü' => 'u'
]);

$esAdmin = in_array(
    $rolNormalizado,
    ['admin', 'administrador'],
    true
);

$esCeo = in_array(
    $rolNormalizado,
    ['ceo', 'ceo aerolinea', 'ceo de aerolinea'],
    true
);

$esPasajero = in_array(
    $rolNormalizado,
    ['usuario', 'pasajero', 'cliente'],
    true
);

/*
|--------------------------------------------------------------------------
| Mapa de páginas públicas
|--------------------------------------------------------------------------
*/

$mapa = [
    'Público' => [
        'Inicio' => 'Pages/Publico/index.php',
        'Buscar vuelos' => 'Pages/Publico/vuelos.php',
        'Aerolíneas' => 'Pages/Publico/aerolineas.php',
        'Promociones' => 'Pages/Publico/promociones.php',
        'Novedades' => 'Pages/Publico/novedades.php',
        'Centro de ayuda' => 'Pages/Publico/ayuda.php',
        'Contacto' => 'Pages/Publico/contacto.php',
        'Mapa del sitio' => 'Pages/Publico/mapa-sitio.php',
    ],
];

/*
|--------------------------------------------------------------------------
| Secciones para visitantes
|--------------------------------------------------------------------------
*/

if (!$estaLogueado) {
    $mapa['Cuenta'] = [
        'Iniciar sesión' => 'Auth/login.php',
        'Registrarse' => 'Auth/registro.php',
    ];
}

/*
|--------------------------------------------------------------------------
| Secciones del pasajero
|--------------------------------------------------------------------------
*/

if ($estaLogueado && $esPasajero) {
    $mapa['Pasajero'] = [
        'Mis reservas' => 'Pages/Pasajero/mis-reservas.php',
        'Historial' => 'Pages/Pasajero/historial.php',
        'Mi perfil' => 'Pages/Pasajero/perfil.php',
    ];
}

/*
|--------------------------------------------------------------------------
| Secciones del CEO
|--------------------------------------------------------------------------
*/

if ($estaLogueado && $esCeo) {
    $mapa['CEO'] = [
        'Inicio del CEO' => 'Pages/Ceo/index.php',
        'Gestionar vuelos' => 'Pages/Ceo/vuelos.php',
        'Promociones' => 'Pages/Ceo/promociones/promociones.php',
        'Reportes' => 'Pages/Ceo/reportes.php',
    ];
}

/*
|--------------------------------------------------------------------------
| Secciones del administrador
|--------------------------------------------------------------------------
*/

if ($estaLogueado && $esAdmin) {
    $mapa['Administración'] = [
        'Gestionar aerolíneas' => 'Pages/Admin/aerolineas/index.php',
        'Gestionar novedades' => 'Pages/Admin/novedades/index.php',
        'Gestionar promociones' => 'Pages/Admin/promociones/promocionesPendientes.php',
        'Reportes' => 'Pages/Admin/reportes/reporteVentas.php',
    ];
}

/*
|--------------------------------------------------------------------------
| Mostrar solamente archivos existentes
|--------------------------------------------------------------------------
|
| Se comprueba la ruta física dentro del proyecto para no mostrar
| enlaces a páginas que todavía no fueron creadas.
|
*/

foreach ($mapa as $seccion => $paginas) {
    foreach ($paginas as $nombre => $ruta) {
        $rutaRelativa = str_replace(
            '/',
            DIRECTORY_SEPARATOR,
            $ruta
        );

        $rutaFisica = $raizProyecto
            . DIRECTORY_SEPARATOR
            . $rutaRelativa;

        if (!is_file($rutaFisica)) {
            unset($mapa[$seccion][$nombre]);
        }
    }

    if (empty($mapa[$seccion])) {
        unset($mapa[$seccion]);
    }
}

/*
|--------------------------------------------------------------------------
| Encabezado y navegación compartidos
|--------------------------------------------------------------------------
*/

require __DIR__ . '/../../Includes/header.php';
require __DIR__ . '/../../Includes/navbar.php';

?>

<main id="contenido-principal" tabindex="-1">

    <!-- Encabezado de la página -->
    <section class="page-header">
        <div class="container">

            <h1>Mapa del sitio</h1>

            <p class="text-muted mb-3">
                Todas las secciones de VOLARA organizadas en un solo lugar.
            </p>

            <nav aria-label="Ruta de navegación">
                <ol class="breadcrumb">

                    <li class="breadcrumb-item">
                        <a
                            href="<?= htmlspecialchars(
                                $baseUrl . '/Pages/Publico/index.php',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >
                            Inicio
                        </a>
                    </li>

                    <li
                        class="breadcrumb-item active"
                        aria-current="page"
                    >
                        Mapa del sitio
                    </li>

                </ol>
            </nav>

        </div>
    </section>

    <!-- Contenido del mapa -->
    <section class="section">
        <div class="container">

            <?php if (!empty($mapa)): ?>

                <div class="row g-4">

                    <?php foreach ($mapa as $seccion => $paginas): ?>

                        <div class="col-md-6 col-lg-4">

                            <div class="volara-card h-100">

                                <h2 class="h5 mb-4">

                                    <?php
                                    switch ($seccion) {
                                        case 'Público':
                                            $iconoSeccion = 'bi-globe';
                                            break;

                                        case 'Cuenta':
                                            $iconoSeccion = 'bi-person-circle';
                                            break;

                                        case 'Pasajero':
                                            $iconoSeccion = 'bi-ticket-perforated';
                                            break;

                                        case 'CEO':
                                            $iconoSeccion = 'bi-speedometer2';
                                            break;

                                        case 'Administración':
                                            $iconoSeccion = 'bi-gear';
                                            break;

                                        default:
                                            $iconoSeccion = 'bi-folder';
                                            break;
                                    }
                                    ?>

                                    <i
                                        class="bi <?= htmlspecialchars(
                                            $iconoSeccion,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?> me-2 text-danger"
                                        aria-hidden="true"
                                    ></i>

                                    <?= htmlspecialchars(
                                        $seccion,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </h2>

                                <ul class="list-unstyled mb-0">

                                    <?php foreach ($paginas as $nombre => $ruta): ?>

                                        <?php
                                        $urlPagina = $baseUrl . '/'
                                            . ltrim($ruta, '/');
                                        ?>

                                        <li class="mb-3">

                                            <a
                                                href="<?= htmlspecialchars(
                                                    $urlPagina,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>"
                                                class="d-flex align-items-center justify-content-between gap-2"
                                            >
                                                <span>
                                                    <i
                                                        class="bi bi-chevron-right me-2 text-muted"
                                                        aria-hidden="true"
                                                    ></i>

                                                    <?= htmlspecialchars(
                                                        $nombre,
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>
                                                </span>

                                                <i
                                                    class="bi bi-arrow-up-right text-muted"
                                                    aria-hidden="true"
                                                ></i>
                                            </a>

                                        </li>

                                    <?php endforeach; ?>

                                </ul>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="volara-card-flat text-center">

                    <i
                        class="bi bi-info-circle fs-2 text-muted"
                        aria-hidden="true"
                    ></i>

                    <h2 class="h5 mt-3">
                        No hay secciones disponibles
                    </h2>

                    <p class="mb-0">
                        No se encontraron páginas para mostrar.
                    </p>

                </div>

            <?php endif; ?>

        </div>
    </section>

</main>

<?php require __DIR__ . '/../../Includes/footer.php'; ?>