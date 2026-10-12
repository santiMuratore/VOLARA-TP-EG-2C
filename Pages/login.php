<?php

require_once __DIR__ . '/Includes/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $mail = $_POST['mail'] ?? '';
    $clave = $_POST['clave'] ?? '';

    $conexion = conectarDB();

    $sql = "
        SELECT
            codUsuario,
            mail,
            clave,
            tipoUsuario,
            codAerolinea
        FROM usuarios
        WHERE mail = :mail
    ";

    $consulta = $conexion->prepare($sql);

    $consulta->execute([
        'mail' => $mail
    ]);

    $usuario = $consulta->fetch(PDO::FETCH_ASSOC);


    if ($usuario && password_verify($clave, $usuario['clave'])) {

        $_SESSION['codUsuario'] = $usuario['codUsuario'];
        $_SESSION['tipoUsuario'] = $usuario['tipoUsuario'];
        $_SESSION['codAerolinea'] = $usuario['codAerolinea'];

         echo 'Login correcto';
         
    } else {

        $error = 'Mail o contraseña incorrectos.';

    }
}
?>


<form method="post">

    <label for="mail">Email</label>
    <input
        type="email"
        id="mail"
        name="mail"
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