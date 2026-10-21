<?php

require_once __DIR__ . '/../Includes/functions.php';

$mensaje = '';
$error = '';

$token = $_GET['token'] ?? '';


if ($token === '') {

    $error = 'El enlace de validación no es válido.';

} else {

    $conexion = conectarDB();

    $sql = "
        SELECT codUsuario, estadoUsuario
        FROM usuarios
        WHERE tokenValidacion = :token
    ";

    $consulta = $conexion->prepare($sql);

    $consulta->execute([
        'token' => $token
    ]);

    $usuario = $consulta->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {

        $error = 'El enlace de validación no existe o ya fue utilizado.';

    } else {

        $sql = "
            UPDATE usuarios
            SET
                estadoUsuario = 'activo',
                tokenValidacion = NULL
            WHERE codUsuario = :codUsuario
        ";

        $consulta = $conexion->prepare($sql);

        $consulta->execute([
            'codUsuario' => $usuario['codUsuario']
        ]);

        $mensaje = 'Tu cuenta fue validada correctamente.';
    }
}
?>

<?php if ($mensaje !== ''): ?>

    <p>
        <?= htmlspecialchars($mensaje) ?>
    </p>

<?php endif; ?>

<?php if ($error !== ''): ?>

    <p>
        <?= htmlspecialchars($error) ?>
    </p>

<?php endif; ?>