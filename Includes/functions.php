<?php

$configLocal =
    __DIR__ . '/../config/config.local.php';

if (is_file($configLocal)) {
    require_once $configLocal;
}

require_once __DIR__ . '/functionsF2.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Zona horaria del sitio: todas las fechas y horas de PHP usan la de Argentina
date_default_timezone_set('America/Argentina/Buenos_Aires');

function conectarDB(): PDO
{
    $host = defined('DB_HOST')
        ? DB_HOST
        : 'localhost';

    $dbname = defined('DB_NAME')
        ? DB_NAME
        : 'volara_db';

    $usuario = defined('DB_USER')
        ? DB_USER
        : 'root';

    $clave = defined('DB_PASS')
        ? DB_PASS
        : '';

    $dsn =
        "mysql:host=$host;"
        . "dbname=$dbname;"
        . "charset=utf8mb4";

    try {
        $conexion = new PDO($dsn, $usuario, $clave);

        $conexion->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );

        return $conexion;

    } catch (PDOException $e) {
        header('Location: /VOLARA-TP-EG-2C/Pages/Publico/index.php');
        exit;
    }
}

function requerirLogin(): void
{
    if (!isset($_SESSION['codUsuario'])) {

        header(
            'Location: '
            . BASE_URL
            . '/Auth/login.php'
        );

        exit;
    }
}

function requerirRol(string $rol): void
{
    requerirLogin();

    if ($_SESSION['tipoUsuario'] !== $rol) {
        header('Location: /VOLARA-TP-EG-2C/Pages/Publico/index.php');
        exit;
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
        session_destroy();
        header('Location: /VOLARA-TP-EG-2C/Pages/Publico/index.php');
        exit;
    }

    return $usuario;
}
function claseInvalida(array $errores, string $campo): string
    {
        return isset($errores[$campo]) ? 'is-invalid' : ''; // Devuelve 'is-invalid' si el campo tiene error.
    }