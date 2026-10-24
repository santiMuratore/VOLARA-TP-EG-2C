
<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/functionsF2.php';

/*
|--------------------------------------------------------------------------
| Configuración y URLs
|--------------------------------------------------------------------------
*/

$baseUrl = rtrim(BASE_URL, '/');

$urlInicio       = $baseUrl . '/Pages/Publico/index.php';
$urlVuelos       = $baseUrl . '/Pages/Publico/vuelos.php';
$urlAerolineas   = $baseUrl . '/Pages/Publico/aerolineas.php';
$urlPromociones  = $baseUrl . '/Pages/Publico/promociones.php';
$urlNovedades    = $baseUrl . '/Pages/Publico/novedades.php';
$urlAyuda        = $baseUrl . '/Pages/Publico/ayuda.php';
$urlContacto     = $baseUrl . '/Pages/Publico/contacto.php';
$urlMapa         = $baseUrl . '/Pages/Publico/mapa-sitio.php';

$urlLogin        = $baseUrl . '/Auth/login.php';
$urlRegistro     = $baseUrl . '/Auth/registro.php';
$urlCerrarSesion = $baseUrl . '/Auth/logout.php';

$urlMisReservas  = $baseUrl . '/Pages/Pasajero/mis-reservas.php';
$urlMiPerfil     = $baseUrl . '/Pages/Pasajero/perfil.php';

$escapar = static function ($valor): string {
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
};

/*
|--------------------------------------------------------------------------
| Usuario actual
|--------------------------------------------------------------------------
*/

$estaLogueado = !empty($_SESSION['codUsuario']);
$usuario = [];

if ($estaLogueado && function_exists('usuarioActual')) {
    $datosUsuario = usuarioActual();

    if (is_array($datosUsuario)) {
        $usuario = $datosUsuario;
    }
}

/*
|--------------------------------------------------------------------------
| Normalización del rol
|--------------------------------------------------------------------------
*/

$rolUsuario = trim((string) (
    $usuario['rol']
    ?? $usuario['tipoUsuario']
    ?? $_SESSION['tipoUsuario']
    ?? ''
));

$rolUsuario = function_exists('mb_strtolower')
    ? mb_strtolower($rolUsuario, 'UTF-8')
    : strtolower($rolUsuario);

$rolNormalizado = strtr($rolUsuario, [
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
| Nombre e iniciales
|--------------------------------------------------------------------------
*/

$nombreUsuario = trim((string) (
    $usuario['nombre']
    ?? $_SESSION['nombre']
    ?? ''
));

$apellidoUsuario = trim((string) (
    $usuario['apellido']
    ?? $_SESSION['apellido']
    ?? ''
));

$nombreMostrar = trim(
    $nombreUsuario . ' ' . $apellidoUsuario
);

$primeraInicial = function_exists('mb_substr')
    ? mb_substr($nombreUsuario, 0, 1, 'UTF-8')
    : substr($nombreUsuario, 0, 1);

$segundaInicial = function_exists('mb_substr')
    ? mb_substr($apellidoUsuario, 0, 1, 'UTF-8')
    : substr($apellidoUsuario, 0, 1);

$iniciales = strtoupper(
    $primeraInicial . $segundaInicial
);

if ($iniciales === '') {
    $iniciales = 'U';
}

/*
|--------------------------------------------------------------------------
| Token CSRF para cerrar sesión
|--------------------------------------------------------------------------
*/

if ($estaLogueado && empty($_SESSION['csrf_logout'])) {
    $_SESSION['csrf_logout'] = bin2hex(random_bytes(32));
}

$csrfCerrarSesion = $_SESSION['csrf_logout'] ?? '';

/*
|--------------------------------------------------------------------------
| Página actual y enlaces activos
|--------------------------------------------------------------------------
*/

$rutaActual = parse_url(
    $_SERVER['REQUEST_URI'] ?? '',
    PHP_URL_PATH
) ?: '';

$rutaNormalizada = strtolower(
    str_replace('\\', '/', $rutaActual)
);

$paginaActual = strtolower(
    basename($rutaNormalizada)
);

$esPaginaPublica = str_contains(
    $rutaNormalizada,
    '/pages/publico/'
);

$esPaginaPasajero = str_contains(
    $rutaNormalizada,
    '/pages/pasajero/'
);

$esPaginaAdmin = str_contains(
    $rutaNormalizada,
    '/pages/admin/'
);

$esPaginaCeo = str_contains(
    $rutaNormalizada,
    '/pages/ceo/'
);

$claseActiva = static function (bool $condicion): string {
    return $condicion ? ' active' : '';
};

/*
|--------------------------------------------------------------------------
| Comprobar páginas de gestión
|--------------------------------------------------------------------------
*/

$tieneVuelosCeo = is_file(
    __DIR__ . '/../Pages/CEO/vuelos.php'
);

$tieneGestionAerolineas = is_file(
    __DIR__ . '/../Pages/Admin/aerolineas/index.php'
);

$tieneGestionNovedades = is_file(
    __DIR__ . '/../Pages/Admin/novedades/index.php'
);

?>

<nav
    class="navbar navbar-expand-xl volara-navbar"
    aria-label="Navegación principal"
>
    <div class="container">

        <!-- Logo de VOLARA -->
        <a
            class="navbar-brand"
            href="<?= $escapar($urlInicio) ?>"
            aria-label="VOLARA - Inicio"
        >
            <img
                src="<?= $escapar(
                    $baseUrl . '/Assets/Imagenes/Volara-Sistema_de_aerolineas.png'
                ) ?>"
                alt="Logo de VOLARA"
                width="54"
                height="54"
            >
        </a>

        <!-- Botón responsive -->
        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#volaraNav"
            aria-controls="volaraNav"
            aria-expanded="false"
            aria-label="Abrir o cerrar el menú"
        >
            <i class="bi bi-list fs-3" aria-hidden="true"></i>
        </button>

        <!-- Menú principal -->
        <div class="collapse navbar-collapse" id="volaraNav">

            <!-- Navegación pública -->
            <ul class="navbar-nav me-auto mb-2 mb-xl-0">

                <!-- Inicio -->
                <li class="nav-item">
                    <a
                        class="nav-link<?= $claseActiva(
                            $paginaActual === 'index.php' && $esPaginaPublica
                        ) ?>"
                        href="<?= $escapar($urlInicio) ?>"
                        <?= $paginaActual === 'index.php' && $esPaginaPublica
                            ? 'aria-current="page"'
                            : '' ?>
                    >
                        <i class="bi bi-house-door me-1" aria-hidden="true"></i>
                        Inicio
                    </a>
                </li>

                <!-- Buscar vuelos -->
                <li class="nav-item">
                    <a
                        class="nav-link<?= $claseActiva(
                            $paginaActual === 'vuelos.php' && $esPaginaPublica
                        ) ?>"
                        href="<?= $escapar($urlVuelos) ?>"
                        <?= $paginaActual === 'vuelos.php' && $esPaginaPublica
                            ? 'aria-current="page"'
                            : '' ?>
                    >
                        <i class="bi bi-search me-1" aria-hidden="true"></i>
                        Buscar vuelos
                    </a>
                </li>

                <!-- Aerolíneas -->
                <li class="nav-item">
                    <a
                        class="nav-link<?= $claseActiva(
                            $paginaActual === 'aerolineas.php' && $esPaginaPublica
                        ) ?>"
                        href="<?= $escapar($urlAerolineas) ?>"
                        <?= $paginaActual === 'aerolineas.php' && $esPaginaPublica
                            ? 'aria-current="page"'
                            : '' ?>
                    >
                        <i class="bi bi-airplane me-1" aria-hidden="true"></i>
                        Aerolíneas
                    </a>
                </li>

                <!-- Menú Descubrí -->
                <?php
                $paginasDescubre = [
                    'promociones.php',
                    'novedades.php',
                    'ayuda.php',
                    'contacto.php'
                ];

                $descubreActivo = $esPaginaPublica
                    && in_array($paginaActual, $paginasDescubre, true);
                ?>

                <li class="nav-item dropdown">
                    <a
                        class="nav-link dropdown-toggle<?= $claseActiva($descubreActivo) ?>"
                        href="#"
                        role="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                    >
                        <i class="bi bi-compass me-1" aria-hidden="true"></i>
                        Descubrí
                    </a>

                    <ul class="dropdown-menu shadow-sm">

                        <li>
                            <a
                                class="dropdown-item<?= $claseActiva(
                                    $paginaActual === 'promociones.php' && $esPaginaPublica
                                ) ?>"
                                href="<?= $escapar($urlPromociones) ?>"
                            >
                                <i class="bi bi-tag me-2" aria-hidden="true"></i>
                                Promociones
                            </a>
                        </li>

                        <li>
                            <a
                                class="dropdown-item<?= $claseActiva(
                                    $paginaActual === 'novedades.php' && $esPaginaPublica
                                ) ?>"
                                href="<?= $escapar($urlNovedades) ?>"
                            >
                                <i class="bi bi-newspaper me-2" aria-hidden="true"></i>
                                Novedades
                            </a>
                        </li>

                        <li>
                            <hr class="dropdown-divider">
                        </li>

                        <li>
                            <a
                                class="dropdown-item<?= $claseActiva(
                                    $paginaActual === 'ayuda.php' && $esPaginaPublica
                                ) ?>"
                                href="<?= $escapar($urlAyuda) ?>"
                            >
                                <i class="bi bi-question-circle me-2" aria-hidden="true"></i>
                                Centro de ayuda
                            </a>
                        </li>

                        <li>
                            <a
                                class="dropdown-item<?= $claseActiva(
                                    $paginaActual === 'contacto.php' && $esPaginaPublica
                                ) ?>"
                                href="<?= $escapar($urlContacto) ?>"
                            >
                                <i class="bi bi-envelope me-2" aria-hidden="true"></i>
                                Contacto
                            </a>
                        </li>

                    </ul>
                </li>

                <!-- Mapa del sitio -->
                <li class="nav-item">
                    <a
                        class="nav-link<?= $claseActiva(
                            $paginaActual === 'mapa-sitio.php'
                        ) ?>"
                        href="<?= $escapar($urlMapa) ?>"
                        <?= $paginaActual === 'mapa-sitio.php'
                            ? 'aria-current="page"'
                            : '' ?>
                    >
                        <i class="bi bi-diagram-3 me-1" aria-hidden="true"></i>
                        Mapa del sitio
                    </a>
                </li>

            </ul>

            <!-- Cuenta y accesos según el rol -->
            <ul class="navbar-nav ms-auto align-items-xl-center gap-xl-1">

                <?php if ($estaLogueado): ?>

                    <!-- Opciones del administrador -->
                    <?php if ($esAdmin): ?>

                        <li class="nav-item dropdown">
                            <a
                                class="nav-link dropdown-toggle<?= $claseActiva($esPaginaAdmin) ?>"
                                href="#"
                                role="button"
                                data-bs-toggle="dropdown"
                                aria-expanded="false"
                            >
                                <i class="bi bi-speedometer2 me-1" aria-hidden="true"></i>
                                Administración
                            </a>

                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">

                                <?php if ($tieneGestionAerolineas): ?>
                                    <li>
                                        <a
                                            class="dropdown-item"
                                            href="<?= $escapar(
                                                $baseUrl . '/Pages/Admin/aerolineas/index.php'
                                            ) ?>"
                                        >
                                            <i class="bi bi-airplane me-2" aria-hidden="true"></i>
                                            Gestionar aerolíneas
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php if ($tieneGestionNovedades): ?>
                                    <li>
                                        <a
                                            class="dropdown-item"
                                            href="<?= $escapar(
                                                $baseUrl . '/Pages/Admin/novedades/index.php'
                                            ) ?>"
                                        >
                                            <i class="bi bi-newspaper me-2" aria-hidden="true"></i>
                                            Gestionar novedades
                                        </a>
                                    </li>
                                <?php endif; ?>

                            </ul>
                        </li>

                    <?php endif; ?>

                    <!-- Opciones del CEO -->
                    <?php if ($esCeo && $tieneVuelosCeo): ?>

                        <li class="nav-item">
                            <a
                                class="nav-link<?= $claseActiva(
                                    $paginaActual === 'vuelos.php' && $esPaginaCeo
                                ) ?>"
                                href="<?= $escapar(
                                    $baseUrl . '/Pages/CEO/vuelos.php'
                                ) ?>"
                                <?= $paginaActual === 'vuelos.php' && $esPaginaCeo
                                    ? 'aria-current="page"'
                                    : '' ?>
                            >
                                <i class="bi bi-airplane me-1" aria-hidden="true"></i>
                                Gestionar vuelos
                            </a>
                        </li>

                    <?php endif; ?>

                    <!-- Menú de usuario para cualquier rol -->
                    <li class="nav-item dropdown user-menu">

                        <a
                            class="nav-link dropdown-toggle d-flex align-items-center gap-2"
                            href="#"
                            role="button"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                        >
                            <span class="user-avatar" aria-hidden="true">
                                <?= $escapar($iniciales) ?>
                            </span>

                            <span class="user-name">
                                <?= $escapar(
                                    $nombreMostrar !== ''
                                        ? $nombreMostrar
                                        : 'Mi cuenta'
                                ) ?>
                            </span>
                        </a>

                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">

                            <!-- Enlaces exclusivos del pasajero -->
                            <?php if ($esPasajero): ?>

                                <li>
                                    <a
                                        class="dropdown-item<?= $claseActiva(
                                            $paginaActual === 'mis-reservas.php' && $esPaginaPasajero
                                        ) ?>"
                                        href="<?= $escapar($urlMisReservas) ?>"
                                    >
                                        <i class="bi bi-ticket-perforated me-2" aria-hidden="true"></i>
                                        Mis reservas
                                    </a>
                                </li>

                                <li>
                                    <a
                                        class="dropdown-item<?= $claseActiva(
                                            $paginaActual === 'perfil.php' && $esPaginaPasajero
                                        ) ?>"
                                        href="<?= $escapar($urlMiPerfil) ?>"
                                    >
                                        <i class="bi bi-person-circle me-2" aria-hidden="true"></i>
                                        Mi perfil
                                    </a>
                                </li>

                                <li>
                                    <hr class="dropdown-divider">
                                </li>

                            <?php endif; ?>

                            <!-- Mapa del sitio para cualquier rol -->
                            <li>
                                <a
                                    class="dropdown-item"
                                    href="<?= $escapar($urlMapa) ?>"
                                >
                                    <i class="bi bi-diagram-3 me-2" aria-hidden="true"></i>
                                    Mapa del sitio
                                </a>
                            </li>

                            <li>
                                <hr class="dropdown-divider">
                            </li>

                            <!-- Cerrar sesión para admin, CEO y pasajero -->
                            <li>
                                <form
                                    method="POST"
                                    action="<?= $escapar($urlCerrarSesion) ?>"
                                >
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= $escapar($csrfCerrarSesion) ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="dropdown-item text-danger"
                                    >
                                        <i
                                            class="bi bi-box-arrow-right me-2"
                                            aria-hidden="true"
                                        ></i>
                                        Cerrar sesión
                                    </button>
                                </form>
                            </li>

                        </ul>
                    </li>

                <?php else: ?>

                    <!-- Visitantes: iniciar sesión -->
                    <li class="nav-item">
                        <a
                            class="nav-link<?= $claseActiva(
                                str_contains($rutaNormalizada, '/auth/login.php')
                            ) ?>"
                            href="<?= $escapar($urlLogin) ?>"
                            <?= str_contains($rutaNormalizada, '/auth/login.php')
                                ? 'aria-current="page"'
                                : '' ?>
                        >
                            <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>
                            Iniciar sesión
                        </a>
                    </li>

                    <!-- Visitantes: registrarse -->
                    <li class="nav-item ms-xl-2">
                        <a
                            class="btn btn-volara btn-volara-sm"
                            href="<?= $escapar($urlRegistro) ?>"
                        >
                            Registrarse
                        </a>
                    </li>

                <?php endif; ?>

            </ul>

        </div>
    </div>
</nav>
