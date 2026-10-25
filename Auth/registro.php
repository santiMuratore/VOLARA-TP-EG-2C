<?php

require_once __DIR__ . '/../Includes/functions.php';
require_once __DIR__ . '/../Includes/mailer.php';

$conexion = conectarDB();

$sqlAerolineas = "
    SELECT codAerolinea, nombre
    FROM aerolineas
    ORDER BY nombre
";

$resultadoAerolineas = $conexion->query($sqlAerolineas);

$aerolineas = $resultadoAerolineas->fetchAll(PDO::FETCH_ASSOC);

$errores = [];

$nombre = '';
$apellido = '';
$mail = '';
$telefono = '';
$tipoUsuario = '';
$codAerolinea = '';
$mensajeExito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $codAerolinea = $_POST['codAerolinea'] ?? '';
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $mail = trim($_POST['mail'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $clave = $_POST['clave'] ?? '';
    $confirmarClave = $_POST['confirmarClave'] ?? '';
    $tipoUsuario = $_POST['tipoUsuario'] ?? '';

    if ($nombre === '') {
    $errores[] = 'El nombre es obligatorio.';
}

if ($apellido === '') {
    $errores[] = 'El apellido es obligatorio.';
}

if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
    $errores[] = 'El email no es válido.';
}

if ($clave === '') {
    $errores[] = 'La contraseña es obligatoria.';
}

if ($clave !== $confirmarClave) {
    $errores[] = 'Las contraseñas no coinciden.';
}

if (!in_array($tipoUsuario, ['usuario', 'ceo aerolínea'], true)) {
    $errores[] = 'El tipo de usuario no es válido.';
}

if ($tipoUsuario === 'ceo aerolínea') {

    if ($codAerolinea === '') {
        $errores[] = 'Debés seleccionar una aerolínea.';
    } else {

            $sql = "
                SELECT codAerolinea
                FROM aerolineas
                WHERE codAerolinea = :codAerolinea
            ";

            $consulta = $conexion->prepare($sql);

            $consulta->execute([
                'codAerolinea' => $codAerolinea
            ]);

            $aerolineaExiste = $consulta->fetch(PDO::FETCH_ASSOC);

            if (!$aerolineaExiste) {
                $errores[] = 'La aerolínea seleccionada no existe.';
            }
        }
    } else {
            $codAerolinea = null;
}

if (empty($errores)) {

    $conexion = conectarDB();

    $sql = "
        SELECT codUsuario
        FROM usuarios
        WHERE mail = :mail
    ";

    $consulta = $conexion->prepare($sql);

    $consulta->execute([
        'mail' => $mail
    ]);

    $usuarioExistente = $consulta->fetch(PDO::FETCH_ASSOC);

    if ($usuarioExistente) {
        $errores[] = 'Ya existe una cuenta registrada con ese email.';
    }
}

if (empty($errores)) {

    $claveHash = password_hash($clave, PASSWORD_DEFAULT);

    if ($tipoUsuario === 'usuario') {

    $estadoUsuario = 'pendiente de validación';

    $tokenValidacion = bin2hex(random_bytes(32));

    } else {

        $estadoUsuario = 'pendiente de autorización';

        $tokenValidacion = null;

    }

}

if (empty($errores)) {

    $sql = "
        INSERT INTO usuarios
        (
            nombre,
            apellido,
            mail,
            clave,
            telefono,
            tipoUsuario,
            codAerolinea,
            estadoUsuario,
            tokenValidacion
        )
        VALUES
        (
            :nombre,
            :apellido,
            :mail,
            :clave,
            :telefono,
            :tipoUsuario,
            :codAerolinea,
            :estadoUsuario,
            :tokenValidacion
        )
    ";

    $consulta = $conexion->prepare($sql);

    $consulta->execute([
        'nombre' => $nombre,
        'apellido' => $apellido,
        'mail' => $mail,
        'clave' => $claveHash,
        'telefono' => $telefono !== '' ? $telefono : null,
        'tipoUsuario' => $tipoUsuario,
        'codAerolinea' => $codAerolinea,
        'estadoUsuario' => $estadoUsuario,
        'tokenValidacion' => $tokenValidacion
    ]);

    if ($tipoUsuario === 'usuario') {

        $enlaceValidacion =
            BASE_URL
            . '/Auth/validarCuenta.php?token='
            . urlencode($tokenValidacion);

        $cuerpo = "
            <h2>Bienvenido a Volara</h2>

            <p>
                Hola " . htmlspecialchars($nombre) . ",
                gracias por registrarte en Volara.
            </p>

            <p>
                Para activar tu cuenta, hacé clic en el siguiente enlace:
            </p>

            <p>
                <a href=\"$enlaceValidacion\">
                    Validar mi cuenta
                </a>
            </p>
        ";

        $mailEnviado = enviarMail(
            $mail,
            'Validá tu cuenta de Volara',
            $cuerpo
        );

        if ($mailEnviado) {

            $mensajeExito =
                'Registro realizado correctamente. Revisá tu correo para activar tu cuenta.';

        } else {

            $mensajeExito =
                'La cuenta fue creada, pero no pudimos enviar el correo de validación.';
        }

    } else {

        $mensajeExito =
            'Registro realizado correctamente. Tu cuenta está pendiente de autorización.';
    }
    
}

}

?>
<?php if ($mensajeExito !== ''): ?>

    <p>
        <?= htmlspecialchars($mensajeExito) ?>
    </p>

<?php endif; ?>
<?php if (!empty($errores)): ?>

    <ul>

        <?php foreach ($errores as $error): ?>

            <li>
                <?= htmlspecialchars($error) ?>
            </li>

        <?php endforeach; ?>

    </ul>

<?php endif; ?>

<form method="post">

    <div>
        <label for="nombre">Nombre</label>
        <input
            type="text"
            id="nombre"
            name="nombre"
            required
        >
    </div>

    <div>
        <label for="apellido">Apellido</label>
        <input
            type="text"
            id="apellido"
            name="apellido"
            required
        >
    </div>

    <div>
        <label for="mail">Email</label>
        <input
            type="email"
            id="mail"
            name="mail"
            required
        >
    </div>

    <div>
        <label for="telefono">Teléfono</label>
        <input
            type="text"
            id="telefono"
            name="telefono"
        >
    </div>

    <div>
        <label for="clave">Contraseña</label>
        <input
            type="password"
            id="clave"
            name="clave"
            required
        >
    </div>

    <div>
        <label for="confirmarClave">Confirmar contraseña</label>
        <input
            type="password"
            id="confirmarClave"
            name="confirmarClave"
            required
        >
    </div>

    <div >
        <label for="tipoUsuario">Tipo de cuenta</label>

        <select id="tipoUsuario" name="tipoUsuario" required>
            <option value="">Seleccionar</option>
            <option value="usuario">Usuario</option>
            <option value="ceo aerolínea">CEO de aerolínea</option>
        </select>
    </div>

    <div id="grupoAerolinea" hidden>
        <label for="codAerolinea">Aerolínea</label>

        <select id="codAerolinea" name="codAerolinea">

            <option value="">Seleccionar aerolínea</option>

            <?php foreach ($aerolineas as $aerolinea): ?>

                <option value="<?= $aerolinea['codAerolinea'] ?>">
                    <?= htmlspecialchars($aerolinea['nombre']) ?>
                </option>

            <?php endforeach; ?>

        </select>
    </div>

    <script>

        const tipoUsuario = document.getElementById('tipoUsuario');
        const grupoAerolinea = document.getElementById('grupoAerolinea');
        const codAerolinea = document.getElementById('codAerolinea');

        tipoUsuario.addEventListener('change', function () {

            if (tipoUsuario.value === 'ceo aerolínea') {

                grupoAerolinea.hidden = false;
                codAerolinea.required = true;

            } else {

                grupoAerolinea.hidden = true;
                codAerolinea.required = false;
                codAerolinea.value = '';

            }

        });

    </script>

    <button type="submit">
        Registrarse
    </button>

</form>

