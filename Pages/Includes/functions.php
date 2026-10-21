<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Módulo Integrante 2 ---

// Definir BASE_URL derivada de la estructura del proyecto
// El valor debe ser /VOLARA-TP-EG-2C para que las URLs funcionen correctamente
// desde cualquier profundidad de carpeta usando __DIR__ relativos
if (!defined('BASE_URL')) {
    // Caminar dos niveles arriba desde la carpeta Includes para llegar a la raíz del proyecto
    $basePath = realpath(__DIR__ . '/../..') ?: dirname(__DIR__);
    $normalized = str_replace('\\', '/', $basePath);
    // Eliminar disco Windows (ej: C:) y obtener solo la carpeta del proyecto
    if (preg_match('/^([A-Za-z]:)\//', $normalized, $m)) {
        $normalized = substr($normalized, strlen($m[1]) + 1);
    }
    // Quitar "/Pages" del final si existe, para obtener la URL base /VOLARA-TP-EG-2C
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
    // Mínimo 1 página
    $pagina = max(1, $pagina);
    // Calcular offset (mínimo 0)
    $offset = max(0, ($pagina - 1) * $porPagina);
    // Total de páginas
    $totalPaginas = ceil($total / $porPagina);
    // Ajustar página si exceeds total
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

    // Anterior
    $anteriorUrl = '';
    if ($pagina > 1) {
        $anteriorPagina = $pagina - 1;
        $anteriorUrl = $urlBase . '?pagina=' . $anteriorPagina;
    }

    // Siguiente
    $siguienteUrl = '';
    if ($pagina < $totalPaginas) {
        $siguientePagina = $pagina + 1;
        $siguienteUrl = $urlBase . '?pagina=' . $siguientePagina;
    }

    // Números de página (mostrar un rango razonable)
    $maxMostrar = 5;
    $inicio = max(1, $pagina - floor($maxMostrar / 2));
    $fin = min($totalPaginas, $inicio + $maxMostrar - 1);
    // Ajustar inicio si el rango se desplaza demasiado
    if ($fin - $inicio < $maxMostrar - 1) {
        $inicio = max(1, $fin - $maxMostrar + 1);
    }

    // Enlace Anterior
    if ($anteriorUrl) {
        $enlaces .= '<li class="page-item"><a class="page-link" href="' . htmlspecialchars($anteriorUrl) . '">Anterior</a></li>';
    }

    // Números de página
    for ($i = $inicio; $i <= $fin; $i++) {
        $active = ($i == $pagina) ? ' class="active"' : '';
        $enlaces .= '<li class="page-item' . $active . '"><a class="page-link" href="' . $urlBase . '?pagina=' . $i . '">' . $i . '</a></li>';
    }

    // Enlace Siguiente
    if ($siguienteUrl) {
        $enlaces .= '<li class="page-item"><a class="page-link" href="' . htmlspecialchars($siguienteUrl) . '">Siguiente</a></li>';
    }

    return $enlaces;
}
// Función para obtener rutas del mapa según el tipo de usuario
function obtenerRutasSitio(string $tipoUsuario): array
{
    $rutas = [];

    // Rutas públicas (siempre disponibles)
    $rutas['publico'] = [
        ['enlace' => 'INICIO', 'url' => BASE_URL . '/Pages/Publico/index.php'],
        ['enlace' => 'AEROLÍNEAS', 'url' => BASE_URL . '/Pages/Publico/aerolineas.php'],
        ['enlace' => 'VUELOS', 'url' => BASE_URL . '/Pages/Publico/vuelos.php'],
        ['enlace' => 'MAPA SITIO', 'url' => BASE_URL . '/Pages/Publico/mapa-sitio.php'],
    ];

    // Rutas según tipo de usuario
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

    // Fusionar: siempre incluyen las rutas públicas + las de su tipo
    $resultado = array_merge($rutas['publico'], $rutas[$tipoUsuario] ?? []);
    return $resultado;
}

// Funciones comunes originales (recuperadas de la versión HEAD de git)

function conectarDB(): PDO
{
    $host = 'localhost';
    $dbname = 'volara_db';
    $usuario = 'root';
    $clave = '';

    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

    try {
        $conexion = new PDO($dsn, $usuario, $clave);

        $conexion->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );

        return $conexion;

    } catch (PDOException $e) {
        die('Error al conectar con la base de datos.');
    }
}

function requerirLogin(): void
{
    if (!isset($_SESSION['codUsuario'])) {
        die('Acceso restringido. Debés iniciar sesión.');
    }
}

function requerirRol(string $rol): void
{
    requerirLogin();

    if ($_SESSION['tipoUsuario'] !== $rol) {
        die('Acceso denegado. No tenés permisos para ingresar.');
    }
}

function usuarioActual(): array
{
    requerirLogin();

    $conexion = conectarDB();

    $sql = "
        SELECT
            codUsuario,
            nombre,
            apellido,
            mail,
            telefono,
            tipoUsuario,
            codAerolinea,
            estadoUsuario
        FROM usuarios
        WHERE codUsuario = :codUsuario
    ";

    $consulta = $conexion->prepare($sql);

    $consulta->execute([
        'codUsuario' => $_SESSION['codUsuario']
    ]);

    $usuario = $consulta->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        die('No se encontró el usuario de la sesión.');
    }

    return $usuario;
}
