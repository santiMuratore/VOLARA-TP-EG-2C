
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
function paginar(int $totalRegistros, int $porPagina = 4, int $paginaActual = 1): array
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
 *
 * @param int    $totalRegistros Cantidad total de registros.
 * @param int    $porPagina      Cantidad de registros por página.
 * @param int    $paginaActual   Página seleccionada.
 * @param string $urlBase        URL de la sección.
 * @param string $parametro      Nombre del parámetro de URL.
 */
function generarPaginacionHTML(
    int $totalRegistros,
    int $porPagina,
    int $paginaActual,
    string $urlBase,
    string $parametro = 'pagina'
): string {
    $porPagina = max(1, $porPagina);
    $totalRegistros = max(0, $totalRegistros);

    $totalPaginas = max(
        1,
        (int) ceil($totalRegistros / $porPagina)
    );

    $paginaActual = max(1, min($paginaActual, $totalPaginas));

    if ($totalPaginas <= 1) {
        return '';
    }

    $html = '<nav aria-label="Paginación">';
    $html .= '<ul class="pagination">';

    for ($p = 1; $p <= $totalPaginas; $p++) {
        $activa = ($p === $paginaActual) ? ' active' : '';
        $ariaActual = ($p === $paginaActual)
            ? ' aria-current="page"'
            : '';

        $separador = str_contains($urlBase, '?') ? '&' : '?';

        $url = $urlBase
            . $separador
            . rawurlencode($parametro)
            . '='
            . $p;

        $urlSegura = htmlspecialchars(
            $url,
            ENT_QUOTES,
            'UTF-8'
        );

        $html .= '<li class="page-item' . $activa . '">';
        $html .= '<a class="page-link" href="' . $urlSegura . '"' . $ariaActual . '>';
        $html .= $p;
        $html .= '</a></li>';
    }

    $html .= '</ul></nav>';

    return $html;
}
/**
 * Devuelve las rutas del sitio público.
 */
/**
 * Devuelve las rutas públicas del sitio.
 */
function obtenerRutasSitio(): array
{
    return [
        [
            'url' => BASE_URL . '/Pages/Publico/index.php',
            'enlace' => 'Inicio',
        ],
        [
            'url' => BASE_URL . '/Pages/Publico/aerolineas.php',
            'enlace' => 'Aerolíneas',
        ],
        [
            'url' => BASE_URL . '/Pages/Publico/vuelos.php',
            'enlace' => 'Vuelos',
        ],
        [
            'url' => BASE_URL . '/Pages/Publico/mapa-sitio.php',
            'enlace' => 'Mapa de sitio',
        ],
    ];
}
