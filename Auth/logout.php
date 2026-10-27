<?php

require_once __DIR__ . '/../Includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header(
        'Location: '
        . rtrim(BASE_URL, '/')
        . '/Pages/Publico/index.php'
    );
    exit;
}

$csrfRecibido =
    $_POST['csrf_token'] ?? '';

$csrfGuardado =
    $_SESSION['csrf_logout'] ?? '';

if (
    !is_string($csrfRecibido) ||
    !is_string($csrfGuardado) ||
    $csrfGuardado === '' ||
    !hash_equals(
        $csrfGuardado,
        $csrfRecibido
    )
) {
    die(
        'La solicitud no es válida. '
        . 'Recargá la página e intentá nuevamente.'
    );
}


$_SESSION = [];

if (ini_get('session.use_cookies')) {

    $parametros =
        session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $parametros['path'],
        $parametros['domain'],
        $parametros['secure'],
        $parametros['httponly']
    );
}


session_destroy();


header(
    'Location: '
    . rtrim(BASE_URL, '/')
    . '/Auth/login.php'
);

exit;