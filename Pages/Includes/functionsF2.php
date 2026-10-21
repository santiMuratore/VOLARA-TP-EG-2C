<?php
// --- Módulo Integrante 2 ---


if (!defined('BASE_URL')) {
    $basePath = realpath(__DIR__ . '/../..') ?: dirname(__DIR__);
    $normalized = str_replace('\\', '/', $basePath);
    if (preg_match('/^([A-Za-z]:)\//', $normalized, $m)) {
        $normalized = substr($normalized, strlen($m[1]) + 1);
    }
    if (preg_match('/^(.+)\/Pages$/', $normalized, $m)) {
        $projectRoot = $m[1];
    } else {
        $projectRoot = $normalized;
    }
    define('BASE_URL', '/' . ltrim(basename($projectRoot), '/'));
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfValido(?string $token): bool
{
    if ($token === null || $token === '') {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function paginar(int $total, int $porPagina = 10, int $pagina = 1): array
{

    $pagina = max(1, $pagina);
    $offset = max(0, ($pagina - 1) * $porPagina);
    $totalPaginas = ceil($total / $porPagina);
    if ($pagina > $totalPaginas && $totalPaginas > 0) {
        $pagina = $totalPaginas;
        $offset = max(0, ($totalPaginas - 1) * $porPagina);
    }

    return [
        'pagina' => $pagina,
        'offset' => $offset,
        'totalPaginas' => $totalPaginas,
        'porPagina' => $porPagina,
    ];
}

function generarPaginacionHTML(int $total, int $porPagina = 10, int $pagina = 1, string $urlBase = ''): string
{
    $pag = paginar($total, $porPagina, $pagina);
    $pagina = $pag['pagina'];
    $offset = $pag['offset'];
    $totalPaginas = $pag['totalPaginas'];

    $enlaces = '';

    if ($totalPaginas <= 1) {
        return $enlaces;
    }

    $anteriorUrl = '';
    if ($pagina > 1) {
        $anteriorPagina = $pagina - 1;
        $anteriorUrl = $urlBase . '?pagina=' . $anteriorPagina;
    }

    $siguienteUrl = '';
    if ($pagina < $totalPaginas) {
        $siguientePagina = $pagina + 1;
        $siguienteUrl = $urlBase . '?pagina=' . $siguientePagina;
    }

    $maxMostrar = 5;
    $inicio = max(1, $pagina - floor($maxMostrar / 2));
    $fin = min($totalPaginas, $inicio + $maxMostrar - 1);

    if ($fin - $inicio < $maxMostrar - 1) {
        $inicio = max(1, $fin - $maxMostrar + 1);
    }

    if ($anteriorUrl) {
        $enlaces .= '<li class="page-item"><a class="page-link" href="' . htmlspecialchars($anteriorUrl) . '">Anterior</a></li>';
    }

    for ($i = $inicio; $i <= $fin; $i++) {
        $active = ($i == $pagina) ? ' class="active"' : '';
        $enlaces .= '<li class="page-item' . $active . '"><a class="page-link" href="' . $urlBase . '?pagina=' . $i . '">' . $i . '</a></li>';
    }

    if ($siguienteUrl) {
        $enlaces .= '<li class="page-item"><a class="page-link" href="' . htmlspecialchars($siguienteUrl) . '">Siguiente</a></li>';
    }

    return $enlaces;
}

function obtenerRutasSitio(string $tipoUsuario): array
{
    $rutas = [];

    $rutas['publico'] = [
        ['enlace' => 'INICIO', 'url' => BASE_URL . '/Pages/Publico/index.php'],
        ['enlace' => 'AEROLÍNEAS', 'url' => BASE_URL . '/Pages/Publico/aerolineas.php'],
        ['enlace' => 'VUELOS', 'url' => BASE_URL . '/Pages/Publico/vuelos.php'],
        ['enlace' => 'MAPA SITIO', 'url' => BASE_URL . '/Pages/Publico/mapa-sitio.php'],
    ];

    switch ($tipoUsuario) {
        case 'administrador':
            $rutas['administrador'] = [
                ['enlace' => 'Panel', 'url' => BASE_URL . '/Pages/Admin/aerolineas/index.php'],
                ['enlace' => 'Gestión Aerolíneas', 'url' => BASE_URL . '/Pages/Admin/aerolineas/index.php'],
                ['enlace' => 'Gestión Novedades', 'url' => BASE_URL . '/Pages/Admin/novedades/index.php'],
                ['enlace' => 'Cerrar sesión', 'url' => BASE_URL . '/Pages/login.php?cerrar=1'],
            ];
            break;
        case 'ceo aerolínea':
            $rutas['ceo'] = [
                ['enlace' => 'Ver novedades', 'url' => BASE_URL . '/Pages/Pasajero/novedades.php'],
                ['enlace' => 'Cerrar sesión', 'url' => BASE_URL . '/Pages/login.php?cerrar=1'],
            ];
            break;
        case 'usuario':
            $rutas['usuario'] = [
                ['enlace' => 'Novedades', 'url' => BASE_URL . '/Pages/Pasajero/novedades.php'],
                ['enlace' => 'Cerrar sesión', 'url' => BASE_URL . '/Pages/login.php?cerrar=1'],
            ];
            break;
    }

    $resultado = array_merge($rutas['publico'], $rutas[$tipoUsuario] ?? []);
    return $resultado;
}
