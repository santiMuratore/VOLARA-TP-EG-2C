<?php
$titulo = $titulo ?? 'Inicio';
$nombreSitio = $nombreSitio ?? 'Volara';
$cuerpoClase = $cuerpoClase ?? '';

$isLoggedIn = isset($_SESSION['codUsuario']) && isset($_SESSION['tipoUsuario']);
$tipoUsuario = $isLoggedIn ? $_SESSION['tipoUsuario'] : 'invitado';
$rutas = obtenerRutasSitio($tipoUsuario);
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

$esActivo = static function (string $url) use ($scriptName): bool {
    $path = parse_url($url, PHP_URL_PATH) ?: '';
    if ($path === '') {
        return false;
    }
    return $scriptName === $path || str_ends_with($scriptName, $path);
};

$inicioUrl = BASE_URL . '/Pages/Publico/index.php';
$loginUrl = BASE_URL . '/Pages/login.php';
$cssUrl = BASE_URL . '/Pages/Assets/style.css';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo) ?> | <?= htmlspecialchars($nombreSitio) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= htmlspecialchars($cssUrl) ?>" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="<?= htmlspecialchars($cuerpoClase) ?>">
<header>
    <nav class="navbar navbar-expand-lg volara-navbar" aria-label="Navegación principal">
        <div class="container">
            <a class="navbar-brand" href="<?= htmlspecialchars($inicioUrl) ?>">
                <span class="brand-mark" aria-hidden="true">V</span>
                <span class="brand-name">VOLARA</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#volaraNav" aria-controls="volaraNav" aria-expanded="false" aria-label="Abrir menú">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="volaraNav">
                <ul class="navbar-nav me-auto">
                    <?php foreach ($rutas as $ruta): ?>
                        <?php
                        if (in_array($ruta['enlace'], ['MAPA SITIO', 'Cerrar sesión'], true)) {
                            continue;
                        }
                        $activo = $esActivo($ruta['url']);
                        ?>
                        <li class="nav-item">
                            <a class="nav-link<?= $activo ? ' active' : '' ?>" href="<?= htmlspecialchars($ruta['url']) ?>"<?= $activo ? ' aria-current="page"' : '' ?>>
                                <?= htmlspecialchars($ruta['enlace']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <li class="nav-item">
                        <?php $mapaActivo = $esActivo(BASE_URL . '/Pages/Publico/mapa-sitio.php'); ?>
                        <a class="nav-link<?= $mapaActivo ? ' active' : '' ?>" href="<?= htmlspecialchars(BASE_URL . '/Pages/Publico/mapa-sitio.php') ?>"<?= $mapaActivo ? ' aria-current="page"' : '' ?>>Mapa de Sitio</a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <?php if ($isLoggedIn): ?>
                        <li class="nav-item dropdown user-menu">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="user-avatar" aria-hidden="true"><?= strtoupper(substr((string) ($tipoUsuario === 'administrador' ? 'A' : 'U'), 0, 1)) ?></span>
                                <span class="d-none d-md-inline">Cuenta</span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><span class="dropdown-item-text text-muted small"><?= htmlspecialchars($tipoUsuario) ?></span></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?= htmlspecialchars(BASE_URL . '/Pages/logout.php') ?>">Cerrar sesión</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= htmlspecialchars($loginUrl) ?>">Iniciar sesión</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= htmlspecialchars(BASE_URL . '/Pages/registro.php') ?>">Registro</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-volara btn-volara-sm nav-cta" href="<?= htmlspecialchars(BASE_URL . '/Pages/Publico/vuelos.php') ?>">Buscar vuelos</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
</header>
