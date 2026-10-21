<?php

require_once __DIR__ . '/../Includes/functions.php';

$error = '';
$mensaje = '';

$token = $_GET['token'] ?? $_POST['token'] ?? '';

$tokenValido = false;

if (preg_match('/^[a-f0-9]{64}$/', $token)) {

    $conexion = conectarDB();

    $sql = "
        SELECT codUsuario
        FROM usuarios
        WHERE tokenRecuperacion = :token
          AND vencimientoToken IS NOT NULL
          AND vencimientoToken > NOW()
    ";

    $consulta = $conexion->prepare($sql);

    $consulta->execute([
        'token' => $token
    ]);

    $usuario = $consulta->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        $tokenValido = true;
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $clave = $_POST['clave'] ?? '';
    $confirmarClave = $_POST['confirmarClave'] ?? '';

    if (!$tokenValido) {

        $error = 'El enlace de recuperación no es válido o ha vencido.';

    } elseif ($clave === '') {

        $error = 'La nueva contraseña es obligatoria.';

    } elseif ($clave !== $confirmarClave) {

        $error = 'Las contraseñas no coinciden.';

    } else {

        $claveHash = password_hash(
            $clave,
            PASSWORD_DEFAULT
        );

        $sql = "
            UPDATE usuarios
            SET
                clave = :clave,
                tokenRecuperacion = NULL,
                vencimientoToken = NULL
            WHERE tokenRecuperacion = :token
              AND vencimientoToken > NOW()
        ";

        $consulta = $conexion->prepare($sql);

        $consulta->execute([
            'clave' => $claveHash,
            'token' => $token
        ]);

        if ($consulta->rowCount() > 0) {

            $mensaje = 'Tu contraseña fue actualizada correctamente.';

            $tokenValido = false;

        } else {

            $error = 'El enlace de recuperación no es válido o ha vencido.';
        }
    }
}

?>

?>

<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <title>Restablecer contraseña - Volara</title>

</head>

<body>

    <h1>Restablecer contraseña</h1>

    <?php if ($mensaje !== ''): ?>

        <p>
            <?= htmlspecialchars($mensaje) ?>
        </p>

        <p>
            <a href="../login.php">
                Iniciar sesión
            </a>
        </p>

    <?php elseif (!$tokenValido): ?>

        <p>
            El enlace de recuperación no es válido o ha vencido.
        </p>

    <?php else: ?>

        <?php if ($error !== ''): ?>

            <p>
                <?= htmlspecialchars($error) ?>
            </p>

        <?php endif; ?>

        <form method="post">

            <input
                type="hidden"
                name="token"
                value="<?= htmlspecialchars($token) ?>"
            >

            <div>

                <label for="clave">
                    Nueva contraseña
                </label>

                <input
                    type="password"
                    id="clave"
                    name="clave"
                    autocomplete="new-password"
                    required
                >

            </div>


            <div>

                <label for="confirmarClave">
                    Confirmar nueva contraseña
                </label>

                <input
                    type="password"
                    id="confirmarClave"
                    name="confirmarClave"
                    autocomplete="new-password"
                    required
                >

            </div>

            <button type="submit">
                Cambiar contraseña
            </button>

        </form>

    <?php endif; ?>

</body>

</html>