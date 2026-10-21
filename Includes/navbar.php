<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/functionsF2.php';

// Obtener el usuario que inició sesión.
$usuario = null;

if (isset($_SESSION['codUsuario'])) {
    $usuario = usuarioActual();
}

if (!is_array($usuario)) {
    $usuario = null;
}

// Obtener rol y datos del usuario.
$rolUsuario = strtolower(
    (string) (
        $usuario['rol']
        ?? $usuario['tipoUsuario']
        ?? $_SESSION['tipoUsuario']
        ?? ''
    )
);

$nombreUsuario = (string) ($usuario['nombre'] ?? '');
$apellidoUsuario = (string) ($usuario['apellido'] ?? '');

$iniciales = strtoupper(
    substr($nombreUsuario, 0, 1)
    . substr($apellidoUsuario, 0, 1)
);

// Detectar página actual para marcar el enlace activo.
$requestPath = parse_url(
    $_SERVER['REQUEST_URI'] ?? '',
    PHP_URL_PATH
) ?: '';

$currentPage = basename($requestPath);

$isActive = static function (array $pages) use ($currentPage): string {
    return in_array($currentPage, $pages, true) ? 'active' : '';
};

// Generar URL dentro del proyecto.
$url = static function (string $ruta): string {
    return BASE_URL . '/' . ltrim($ruta, '/');
};

// Escapar valores antes de imprimirlos en HTML.
$e = static function ($valor): string {
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
};

// Detectar páginas que realmente existen.
$paginaExiste = static function (string $ruta): bool {
    return is_file(
        dirname(__DIR__) . DIRECTORY_SEPARATOR
        . str_replace('/', DIRECTORY_SEPARATOR, $ruta)
    );
};

// Estas secciones son opcionales: solo aparecen si sus archivos existen.
$tienePromociones = $paginaExiste('Publico/promociones.php');
$tieneNovedades = $paginaExiste('Publico/novedades.php');
$tieneAyuda = $paginaExiste('Publico/ayuda.php');
?>

<link
    href="<?= $e($url('Pages/Assets/style.css')) ?>"
    rel="stylesheet"
>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    rel="stylesheet"
>

<link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
    rel="stylesheet"
>

<nav
    class="navbar navbar-expand-xl volara-navbar"
    aria-label="Navegación principal"
>
    <div class="container">

        <!-- Logo e inicio -->
        <a
            class="navbar-brand"
            href="<?= $e($url('Pages/Publico/index.php')) ?>"
            aria-label="VOLARA - Inicio"
        >
            <img
                src="<?= $e($url('Assets/Imagenes/Volara-Sistema_de_aerolineas.png')) ?>"
                alt="Logo de VOLARA"
                width="54"
                height="54"
                style="object-fit: contain;"
            >
        </a>

        <!-- Menú móvil -->
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

        <!-- Navegación -->
        <div class="collapse navbar-collapse" id="volaraNav">

            <ul class="navbar-nav me-auto mb-2 mb-xl-0">

                <!-- Inicio -->
                <li class="nav-item">
                    <a
                        class="nav-link <?= $isActive(['index.php']) ?>"
                        href="<?= $e($url('Pages/Publico/index.php')) ?>"
                    >
                        <i class="bi bi-house-door me-1" aria-hidden="true"></i>
                        Inicio
                    </a>
                </li>

                <!-- Búsqueda de vuelos -->
                <li class="nav-item">
                    <a
                        class="nav-link <?= $isActive(['vuelos.php']) ?>"
                        href="<?= $e($url('Pages/Publico/vuelos.php')) ?>"
                    >
                        <i class="bi bi-search me-1" aria-hidden="true"></i>
                        Buscar vuelos
                    </a>
                </li>

                <!-- Aerolíneas -->
                <li class="nav-item">
                    <a
                        class="nav-link <?= $isActive(['aerolineas.php']) ?>"
                        href="<?= $e($url('Pages/Publico/aerolineas.php')) ?>"
                    >
                        <i class="bi bi-airplane me-1" aria-hidden="true"></i>
                        Aerolíneas
                    </a>
                </li>

                <!-- Descubre -->
                <li class="nav-item dropdown">
                    <a
                        class="nav-link dropdown-toggle"
                        href="#"
                        role="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                    >
                        <i class="bi bi-compass me-1" aria-hidden="true"></i>
                        Descubre
                    </a>

                    <ul class="dropdown-menu shadow-sm">

                        <li>
                            <a
                                class="dropdown-item"
                                href="<?= $e($url('Pages/Publico/vuelos.php')) ?>"
                            >
                                <i class="bi bi-geo-alt me-2" aria-hidden="true"></i>
                                Destinos y vuelos
                            </a>
                        </li>

                        <?php if ($tienePromociones): ?>
                            <li>
                                <a
                                    class="dropdown-item"
                                    href="<?= $e($url('Pages/Publico/promociones.php')) ?>"
                                >
                                    <i class="bi bi-tag me-2" aria-hidden="true"></i>
                                    Ofertas y promociones
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php if ($tieneNovedades): ?>
                            <li>
                                <a
                                    class="dropdown-item"
                                    href="<?= $e($url('Pages/Publico/novedades.php')) ?>"
                                >
                                    <i class="bi bi-newspaper me-2" aria-hidden="true"></i>
                                    Novedades
                                </a>
                            </li>
                        <?php endif; ?>

                    </ul>
                </li>

                <!-- Centro de ayuda: solo si existe la página -->
                <?php if ($tieneAyuda): ?>
                    <li class="nav-item">
                        <a
                            class="nav-link <?= $isActive(['ayuda.php']) ?>"
                            href="<?= $e($url('Pages/Publico/ayuda.php')) ?>"
                        >
                            <i class="bi bi-question-circle me-1" aria-hidden="true"></i>
                            Centro de ayuda
                        </a>
                    </li>
                <?php endif; ?>

                <!-- Mapa del sitio -->
                <li class="nav-item">
                    <a
                        class="nav-link <?= $isActive(['mapa-sitio.php']) ?>"
                        href="<?= $e($url('Pages/Publico/mapa-sitio.php')) ?>"
                    >
                        Mapa de sitio
                    </a>
                </li>

            </ul>

            <!-- Opciones de usuario -->
            <ul class="navbar-nav ms-auto align-items-xl-center gap-xl-1">

                <?php if ($usuario): ?>

                    <!-- Opciones del administrador -->
                    <?php if (in_array($rolUsuario, ['admin', 'administrador'], true)): ?>

                        <li class="nav-item dropdown">
                            <a
                                class="nav-link dropdown-toggle"
                                href="#"
                                role="button"
                                data-bs-toggle="dropdown"
                                aria-expanded="false"
                            >
                                <i class="bi bi-speedometer2 me-1" aria-hidden="true"></i>
                                Administración
                            </a>

                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">

                                <li>
                                    <a
                                        class="dropdown-item"
                                        href="<?= $e($url('Pages/Admin/aerolineas/index.php')) ?>"
                                    >
                                        <i class="bi bi-airplane me-2" aria-hidden="true"></i>
                                        Gestionar aerolíneas
                                    </a>
                                </li>

                                <li>
                                    <a
                                        class="dropdown-item"
                                        href="<?= $e($url('Pages/Admin/novedades/index.php')) ?>"
                                    >
                                        <i class="bi bi-newspaper me-2" aria-hidden="true"></i>
                                        Gestionar novedades
                                    </a>
                                </li>

                            </ul>
                        </li>

                    <?php endif; ?>

                    <!-- Opciones del CEO -->
                    <?php if ($rolUsuario === 'ceo'): ?>

                        <?php if ($paginaExiste('CEO/vuelos.php')): ?>
                            <li class="nav-item">
                                <a
                                    class="nav-link"
                                    href="<?= $e($url('Pages/CEO/vuelos.php')) ?>"
                                >
                                    <i class="bi bi-airplane me-1" aria-hidden="true"></i>
                                    Gestionar vuelos
                                </a>
                            </li>
                        <?php endif; ?>

                    <?php endif; ?>

                    <!-- Menú personal -->
                    <li class="nav-item dropdown user-menu">

                        <a
                            class="nav-link dropdown-toggle d-flex align-items-center gap-2"
                            href="#"
                            role="button"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                        >
                            <span
                                class="user-avatar"
                                aria-hidden="true"
                            >
                                <?= $e($iniciales ?: 'U') ?>
                            </span>

                            <span class="user-name">
                                <?= $e($nombreUsuario ?: 'Mi cuenta') ?>
                            </span>
                        </a>

                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">

                            <?php if ($rolUsuario === 'pasajero'): ?>
                                <li>
                                    <a
                                        class="dropdown-item"
                                        href="<?= $e($url('Pages/Pasajero/novedades.php')) ?>"
                                    >
                                        <i class="bi bi-newspaper me-2" aria-hidden="true"></i>
                                        Novedades
                                    </a>
                                </li>
                            <?php endif; ?>

                            <li>
                                <a
                                    class="dropdown-item"
                                    href="<?= $e($url('Pages/Publico/mapa-sitio.php')) ?>"
                                >
                                    <i class="bi bi-diagram-3 me-2" aria-hidden="true"></i>
                                    Mapa de sitio
                                </a>
                            </li>

                        </ul>

                    </li>

                <?php else: ?>

                    <!-- Invitado: modificar estas rutas si la autenticación
                         se encuentra en otra carpeta del proyecto. -->
                    <li class="nav-item">
                        <a
                            class="nav-link"
                            href="<?= $e($url('Pages/Publico/login.php')) ?>"
                        >
                            <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>
                            Iniciar sesión
                        </a>
                    </li>

                    <li class="nav-item ms-xl-2">
                        <a
                            class="btn btn-volara btn-volara-sm"
                            href="<?= $e($url('Pages/Publico/registro.php')) ?>"
                        >
                            Registrarse
                        </a>
                    </li>

                <?php endif; ?>

            </ul>

        </div>
    </div>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>