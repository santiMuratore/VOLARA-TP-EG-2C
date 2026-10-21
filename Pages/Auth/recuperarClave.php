<?php

require_once __DIR__ . '/../Includes/functions.php';
require_once __DIR__ . '/../Includes/mailer.php';

$mensaje = '';
$error = '';
$mail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $mail = trim($_POST['mail'] ?? '');

    if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {

        $error = 'Ingresá un correo válido.';

    } else {

        $conexion = conectarDB();

        $sql = "
            SELECT codUsuario, mail
            FROM usuarios
            WHERE mail = :mail
        ";

        $consulta = $conexion->prepare($sql);

        $consulta->execute([
            'mail' => $mail
        ]);

        $usuario = $consulta->fetch(PDO::FETCH_ASSOC);

        if ($usuario) {

            $tokenRecuperacion = bin2hex(random_bytes(32));

            $sql = "
                UPDATE usuarios
                SET
                    tokenRecuperacion = :tokenRecuperacion,
                    vencimientoToken = DATE_ADD(NOW(), INTERVAL 1 HOUR)
                WHERE codUsuario = :codUsuario
            ";

            $consulta = $conexion->prepare($sql);

            $consulta->execute([
                'tokenRecuperacion' => $tokenRecuperacion,
                'codUsuario' => $usuario['codUsuario']
            ]);

            $enlaceRecuperacion =
                BASE_URL
                . '/Auth/restablecerClave.php?token='
                . urlencode($tokenRecuperacion);

            $cuerpo = "
                <h2>Recuperación de contraseña</h2>

                <p>
                    Recibimos una solicitud para cambiar la contraseña de tu cuenta de Volara.
                </p>

                <p>
                    El siguiente enlace será válido durante una hora:
                </p>

                <p>
                    <a href=\"$enlaceRecuperacion\">
                        Restablecer mi contraseña
                    </a>
                </p>

                <p>
                    Si no solicitaste este cambio, podés ignorar este correo.
                </p>
            ";

            enviarMail(
                $usuario['mail'],
                'Recuperá tu contraseña de Volara',
                $cuerpo
            );
        }
        $mensaje =
            'Si existe una cuenta asociada a ese correo, recibirás un enlace para recuperar tu contraseña.';
    }
}

?>

<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <title>Recuperar contraseña - Volara</title>

</head>

<body>

    <h1>Recuperar contraseña</h1>

    <p>
        Ingresá el correo asociado a tu cuenta.
    </p>

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


    <form method="post">

        <div>

            <label for="mail">
                Email
            </label>

            <input
                type="email"
                id="mail"
                name="mail"
                value="<?= htmlspecialchars($mail) ?>"
                required
            >

        </div>

        <button type="submit">
            Enviar enlace de recuperación
        </button>

    </form>

</body>

</html>