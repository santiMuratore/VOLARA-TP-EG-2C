<?php

require_once __DIR__ . '/../Includes/functions.php';
require_once __DIR__ . '/../Includes/mailer.php';

$errores = [];
$aerolineas = [];
$conexion = null;

try {

    $conexion = conectarDB();

    $sqlAerolineas = "
        SELECT codAerolinea, nombre
        FROM aerolineas
        ORDER BY nombre
    ";

    $resultadoAerolineas = $conexion->query($sqlAerolineas);

    $aerolineas = $resultadoAerolineas->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log('Error cargando aerolíneas: ' . $e->getMessage());
}

$nombre = '';
$apellido = '';
$mail = '';
$telefono = '';
$tipoUsuario = '';
$codAerolinea = '';
$mensajeExito = '';
$registroOk = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $codAerolinea = trim((string) ($_POST['codAerolinea'] ?? ''));
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $mail = trim($_POST['mail'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $clave = $_POST['clave'] ?? '';
    $confirmarClave = $_POST['confirmarClave'] ?? '';
    $tipoUsuario = $_POST['tipoUsuario'] ?? '';

    $tokenRecibido =
    $_POST['csrf_token'] ?? null;

    if (
        !is_string($tokenRecibido) ||
        !csrfValido($tokenRecibido)
    ) {
        $errores[] =
            'La solicitud no es válida. Recargá la página e intentá nuevamente.';
    }

    if ($nombre === '') {

    $errores[] = 'El nombre es obligatorio.';

} elseif (mb_strlen($nombre) < 2) {

    $errores[] = 'El nombre debe contener al menos 2 caracteres.';
}

if ($apellido === '') {

    $errores[] = 'El apellido es obligatorio.';

} elseif (mb_strlen($apellido) < 2) {

    $errores[] = 'El apellido debe contener al menos 2 caracteres.';
}

if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {

    $errores[] = 'El email no es válido.';

} elseif (strlen($mail) > 254) {

    $errores[] = 'El email es demasiado largo.';
}

if (strlen($telefono) > 30) {

    $errores[] = 'El teléfono no puede superar los 30 caracteres.';
}

if (strlen($clave) < 8) {

    $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
}

if ($clave !== $confirmarClave) {

    $errores[] = 'Las contraseñas no coinciden.';
}

if (!in_array($tipoUsuario, ['usuario', 'ceo aerolínea'], true)) {
    $errores[] = 'El tipo de usuario no es válido.';
}
if (!$conexion) {
    $errores[] = 'No se pudo conectar con la base de datos. Intentá nuevamente.';
}

    if ($tipoUsuario === 'ceo aerolínea') {

        if ($codAerolinea === '') {

            $errores[] = 'Debés seleccionar una aerolínea.';

        } elseif ($conexion) {

            $sql = "
                SELECT codAerolinea
                FROM aerolineas
                WHERE codAerolinea = :codAerolinea
                LIMIT 1
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


    $sql = "
        SELECT codUsuario
        FROM usuarios
        WHERE mail = :mail
        LIMIT 1
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

    try {

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

    $registroOk = true;

} catch (PDOException $e) {

    error_log('Error de registro: ' . $e->getMessage());

    $errores[] =
        'No se pudo completar el registro. Verificá los datos e intentá nuevamente.';
}
if ($registroOk) {

    if ($tipoUsuario === 'usuario') {

        $enlaceValidacion =
            rtrim(BASE_URL, '/')
            . '/Auth/validarCuenta.php?token='
            . rawurlencode($tokenValidacion);

        $enlaceSeguro =
            htmlspecialchars($enlaceValidacion, ENT_QUOTES, 'UTF-8');

        $nombreSeguro =
            htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');

        $cuerpo = plantillaValidacionVolara(
            $nombre,
            $enlaceValidacion
        );

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
                'La cuenta fue creada, pero no pudimos enviar el correo de validación. Podés reenviarlo desde la pantalla de inicio de sesión.';
        }

    } else {

        $mensajeExito =
            'Registro realizado correctamente. Tu cuenta está pendiente de autorización.';
    }

    $nombre = '';
    $apellido = '';
    $mail = '';
    $telefono = '';
    $tipoUsuario = '';
    $codAerolinea = '';
}
}
    
}

?>
<?php

$titulo = 'Crear cuenta';

require_once __DIR__ . '/../Includes/header.php';
require_once __DIR__ . '/../Includes/navbar.php';

?>
<main class="section section-gray">

    <div class="container">

        <div class="row justify-content-center">

            <div class="col-lg-8 col-xl-7">

                <div class="volara-card">

                    <!-- Encabezado del formulario -->
                    <div class="text-center mb-4">

                        <span class="eyebrow">
                            Cuenta Volara
                        </span>

                        <h1 class="section-title mt-2 mb-2">
                            Crear cuenta
                        </h1>

                        <p class="text-secondary mb-0">
                            Completá tus datos para registrarte en Volara.
                        </p>

                    </div>


                    
                    <?php if ($mensajeExito !== ''): ?>

                        <div class="volara-alert alert-success">

                            <i
                                class="bi bi-check-circle-fill"
                                aria-hidden="true"
                            ></i>

                            <span>
                                <?= htmlspecialchars(
                                    $mensajeExito,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </span>

                        </div>

                    <?php endif; ?>


                    
                    <?php if (!empty($errores)): ?>

                        <div class="volara-alert alert-danger align-items-start">

                            <i
                                class="bi bi-exclamation-circle-fill mt-1"
                                aria-hidden="true"
                            ></i>

                            <div>

                                <strong>
                                    Revisá los siguientes datos:
                                </strong>

                                <ul class="mb-0 mt-2 ps-3">

                                    <?php foreach ($errores as $error): ?>

                                        <li>
                                            <?= htmlspecialchars(
                                                $error,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </li>

                                    <?php endforeach; ?>

                                </ul>

                            </div>

                        </div>

                    <?php endif; ?>


                    <form method="post">
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars(
                                csrfToken(),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >

                        <div class="row g-3">

                            
                            <div class="col-md-6">

                                <div class="form-group">

                                    <label
                                        for="nombre"
                                        class="volara-label"
                                    >
                                        Nombre
                                    </label>

                                    <input
                                        type="text"
                                        id="nombre"
                                        name="nombre"
                                        class="volara-input"
                                        value="<?= htmlspecialchars(
                                            $nombre,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        minlength="2"
                                        maxlength="100"
                                        required
                                    >

                                </div>

                            </div>


                            
                            <div class="col-md-6">

                                <div class="form-group">

                                    <label
                                        for="apellido"
                                        class="volara-label"
                                    >
                                        Apellido
                                    </label>

                                    <input
                                        type="text"
                                        id="apellido"
                                        name="apellido"
                                        class="volara-input"
                                        value="<?= htmlspecialchars(
                                            $apellido,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        minlength="2"
                                        maxlength="100"
                                        required
                                    >

                                </div>

                            </div>


                            <!-- Email -->
                            <div class="col-md-6">

                                <div class="form-group">

                                    <label
                                        for="mail"
                                        class="volara-label"
                                    >
                                        Email
                                    </label>

                                    <input
                                        type="email"
                                        id="mail"
                                        name="mail"
                                        class="volara-input"
                                        value="<?= htmlspecialchars(
                                            $mail,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        maxlength="254"
                                        placeholder="nombre@correo.com"
                                        required
                                    >

                                </div>

                            </div>


                            
                            <div class="col-md-6">

                                <div class="form-group">

                                    <label
                                        for="telefono"
                                        class="volara-label"
                                    >
                                        Teléfono
                                    </label>

                                    <input
                                        type="text"
                                        id="telefono"
                                        name="telefono"
                                        class="volara-input"
                                        value="<?= htmlspecialchars(
                                            $telefono,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        maxlength="30"
                                        placeholder="Ej. 341 1234567"
                                    >

                                </div>

                            </div>


                            
                            <div class="col-md-6">

                                <div class="form-group">

                                    <label
                                        for="clave"
                                        class="volara-label"
                                    >
                                        Contraseña
                                    </label>

                                    <input
                                        type="password"
                                        id="clave"
                                        name="clave"
                                        class="volara-input"
                                        minlength="8"
                                        placeholder="Mínimo 8 caracteres"
                                        required
                                    >

                                </div>

                            </div>


                            
                            <div class="col-md-6">

                                <div class="form-group">

                                    <label
                                        for="confirmarClave"
                                        class="volara-label"
                                    >
                                        Confirmar contraseña
                                    </label>

                                    <input
                                        type="password"
                                        id="confirmarClave"
                                        name="confirmarClave"
                                        class="volara-input"
                                        minlength="8"
                                        placeholder="Repetí tu contraseña"
                                        required
                                    >

                                </div>

                            </div>


                            
                            <div class="col-12">

                                <div class="form-group">

                                    <label
                                        for="tipoUsuario"
                                        class="volara-label"
                                    >
                                        Tipo de cuenta
                                    </label>

                                    <select
                                        id="tipoUsuario"
                                        name="tipoUsuario"
                                        class="volara-select"
                                        required
                                    >

                                        <option value="">
                                            Seleccionar tipo de cuenta
                                        </option>

                                        <option
                                            value="usuario"
                                            <?= $tipoUsuario === 'usuario'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            Usuario
                                        </option>

                                        <option
                                            value="ceo aerolínea"
                                            <?= $tipoUsuario === 'ceo aerolínea'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            CEO de aerolínea
                                        </option>

                                    </select>

                                </div>

                            </div>


                            
                            <div
                                class="col-12"
                                id="grupoAerolinea"
                                hidden
                            >

                                <div class="form-group">

                                    <label
                                        for="codAerolinea"
                                        class="volara-label"
                                    >
                                        Aerolínea
                                    </label>

                                    <select
                                        id="codAerolinea"
                                        name="codAerolinea"
                                        class="volara-select"
                                    >

                                        <option value="">
                                            Seleccionar aerolínea
                                        </option>

                                        <?php foreach ($aerolineas as $aerolinea): ?>

                                            <option
                                                value="<?= (int) $aerolinea['codAerolinea'] ?>"
                                                <?= (string) $codAerolinea ===
                                                    (string) $aerolinea['codAerolinea']
                                                    ? 'selected'
                                                    : '' ?>
                                            >
                                                <?= htmlspecialchars(
                                                    $aerolinea['nombre'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>

                            </div>

                        </div>


                        <!-- Botón -->
                        <button
                            type="submit"
                            class="btn-volara btn-volara-lg w-100 mt-2"
                        >
                            <i
                                class="bi bi-person-plus"
                                aria-hidden="true"
                            ></i>

                            Crear cuenta
                        </button>


                        
                        <p class="text-center text-secondary mt-4 mb-0">

                            ¿Ya tenés una cuenta?

                            <a
                                href="<?= BASE_URL ?>/Auth/login.php"
                                class="fw-semibold"
                            >
                                Iniciar sesión
                            </a>

                        </p>

                    </form>

                </div>

            </div>

        </div>

    </div>

</main>

<script>

    const tipoUsuario =
        document.getElementById('tipoUsuario');

    const grupoAerolinea =
        document.getElementById('grupoAerolinea');

    const codAerolinea =
        document.getElementById('codAerolinea');


    function actualizarAerolinea() {

        if (tipoUsuario.value === 'ceo aerolínea') {

            grupoAerolinea.hidden = false;
            codAerolinea.required = true;

        } else {

            grupoAerolinea.hidden = true;
            codAerolinea.required = false;
            codAerolinea.value = '';
        }
    }


    tipoUsuario.addEventListener(
        'change',
        actualizarAerolinea
    );

    actualizarAerolinea();

</script>

<?php
require_once __DIR__ . '/../Includes/footer.php';
?>

