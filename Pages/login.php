<?php

require_once __DIR__ . '/Includes/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $mail = trim($_POST['mail'] ?? '');
    $clave = $_POST['clave'] ?? '';

    $conexion = conectarDB();

    $sql = "
        SELECT
            codUsuario,
            mail,
            clave,
            tipoUsuario,
            codAerolinea,
            estadoUsuario
        FROM usuarios
        WHERE mail = :mail
    ";

    $consulta = $conexion->prepare($sql);

    $consulta->execute([
        'mail' => $mail
    ]);

    $usuario = $consulta->fetch(PDO::FETCH_ASSOC);


    if (!$usuario || !password_verify($clave, $usuario['clave'])) {

        $error = 'Mail o contraseña incorrectos.';

    } elseif ($usuario['estadoUsuario'] === 'pendiente de validación') {

        $error = 'Debés validar tu cuenta desde el enlace que enviamos a tu correo.';

    } elseif ($usuario['estadoUsuario'] === 'pendiente de autorización') {

        $error = 'Tu cuenta está pendiente de autorización por un administrador.';

    } elseif ($usuario['estadoUsuario'] === 'rechazado') {

        $error = 'Tu cuenta fue rechazada y no puede iniciar sesión.';

    } elseif ($usuario['estadoUsuario'] === 'activo') {

        session_regenerate_id(true);

        $_SESSION['codUsuario'] = $usuario['codUsuario'];
        $_SESSION['tipoUsuario'] = $usuario['tipoUsuario'];
        $_SESSION['codAerolinea'] = $usuario['codAerolinea'];

        echo 'Login correcto';
    }
}
?>


<form method="post">

    <label for="mail">Email</label>
    <input
        type="email"
        id="mail"
        name="mail"
        value="<?= htmlspecialchars($mail) ?>"
        required
    >

    <br>

    <label for="clave">Contraseña</label>
    <input
        type="password"
        id="clave"
        name="clave"
        required
    >

    <br>

    <button type="submit">
        Iniciar sesión
    </button>

</form>

<?php if ($error !== ''): ?>

    <p><?= htmlspecialchars($error) ?></p>

<?php endif; ?>