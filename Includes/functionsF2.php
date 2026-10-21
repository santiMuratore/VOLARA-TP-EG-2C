
<?php

// Funciones del módulo Fase 2.

// Garantiza que exista una sesión para las funciones CSRF.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// URL base del proyecto.
if (!defined('BASE_URL')) {
    $directorioProyecto = '/VOLARA-TP-EG-2C';
    define('BASE_URL', $directorioProyecto);
}

/**
 * Obtiene o genera el token CSRF de la sesión.
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Valida el token CSRF recibido.
 */
function csrfValido(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Calcula los datos necesarios para paginar resultados.
 */
function paginar(int $totalRegistros, int $porPagina = 10, int $paginaActual = 1): array
{
    $porPagina = max(1, $porPagina);
    $totalRegistros = max(0, $totalRegistros);

    $totalPaginas = max(1, (int) ceil($totalRegistros / $porPagina));
    $paginaActual = max(1, min($paginaActual, $totalPaginas));

    return [
        'total' => $totalRegistros,
        'porPagina' => $porPagina,
        'paginaActual' => $paginaActual,
        'totalPaginas' => $totalPaginas,
        'offset' => ($paginaActual - 1) * $porPagina,
    ];
}

/**
 * Genera los enlaces HTML de paginación.
 */
function generarPaginacionHTML(
    int $paginaActual,
    int $totalPaginas,
    string $parametro = 'pagina'
): string {
    if ($totalPaginas <= 1) {
        return '';
    }

    $paginaActual = max(1, min($paginaActual, $totalPaginas));
    $html = '<nav aria-label="Paginación"><ul class="pagination">';

    for ($pagina = 1; $pagina <= $totalPaginas; $pagina++) {
        $activa = $pagina === $paginaActual ? ' active' : '';
        $ariaActual = $pagina === $paginaActual
            ? ' aria-current="page"'
            : '';

        $url = htmlspecialchars(
            '?' . rawurlencode($parametro) . '=' . $pagina,
            ENT_QUOTES,
            'UTF-8'
        );

        $html .= '<li class="page-item' . $activa . '">';
        $html .= '<a class="page-link" href="' . $url . '"' . $ariaActual . '>';
        $html .= $pagina;
        $html .= '</a></li>';
    }

    $html .= '</ul></nav>';

    return $html;
}

/**
 * Devuelve las rutas del sitio público.
 */
function obtenerRutasSitio(): array
{
    return [
        'inicio' => BASE_URL . '/Pages/index.php',
        'aerolineas' => BASE_URL . '/Pages/Publico/aerolineas.php',
        'novedades' => BASE_URL . '/Pages/Publico/novedades.php',
        'mapa_sitio' => BASE_URL . '/Pages/Publico/mapa-sitio.php',
    ];
}
