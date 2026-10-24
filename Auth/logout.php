
<?php

/*
|--------------------------------------------------------------------------
| Iniciar sesión
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Cargar funciones y configuración
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../Includes/functions.php';
require_once __DIR__ . '/../Includes/functionsF2.php';

$baseUrl = rtrim(BASE_URL, '/');

/*
|--------------------------------------------------------------------------
| Aceptar únicamente POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $baseUrl . '/Pages/Publico/index.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Validar token CSRF
|--------------------------------------------------------------------------
*/

$tokenRecibido = $_POST['csrf_token'] ?? '';
$tokenSesion = $_SESSION['csrf_logout'] ?? '';

if (
    !is_string($tokenRecibido) ||
    !is_string($tokenSesion) ||
    $tokenRecibido === '' ||
    $tokenSesion === '' ||
    !hash_equals($tokenSesion, $tokenRecibido)
) {
    http_response_code(403);
    exit('La solicitud de cierre de sesión no es válida. Recargá la página e intentá nuevamente.');
}

/*
|--------------------------------------------------------------------------
| Eliminar todos los datos de la sesión
|--------------------------------------------------------------------------
*/

// Elimina codUsuario, tipoUsuario, codAerolinea y los demás datos.
$_SESSION = [];

/*
|--------------------------------------------------------------------------
| Eliminar la cookie de sesión
|--------------------------------------------------------------------------
*/

if (ini_get('session.use_cookies')) {
    $parametrosCookie = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        [
            'expires' => time() - 42000,
            'path' => $parametrosCookie['path'],
            'domain' => $parametrosCookie['domain'],
            'secure' => $parametrosCookie['secure'],
            'httponly' => $parametrosCookie['httponly'],
            'samesite' => $parametrosCookie['samesite'] ?? 'Lax',
        ]
    );
}

/*
|--------------------------------------------------------------------------
| Destruir la sesión
|--------------------------------------------------------------------------
*/

session_destroy();

/*
|--------------------------------------------------------------------------
| Redirigir al inicio público
|--------------------------------------------------------------------------
*/

header('Location: ' . $baseUrl . '/Pages/Publico/index.php');
exit;
