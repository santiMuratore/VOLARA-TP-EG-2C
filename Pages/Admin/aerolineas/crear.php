<?php
require_once __DIR__ . '/../../Includes/functions.php';

// Título de la página
$titulo = 'Crear Aerolínea';

// Control de acceso: solo administradores
requerirRol('administrador');

// Variables iniciales
$errores = [];
$mensaje = '';
$flashTipo = 'info';

// Datos del formulario para conservarlos si hay errores
$codigoIATAOld = '';
$nombreOld = '';
$paisOld = '';

// Mensaje recibido por GET, si existe
if (isset($_GET['mensaje'])) {
    $mensaje = (string) $_GET['mensaje'];

    $tipoRecibido = $_GET['tipo'] ?? 'info';

    $flashTipo = in_array(
        $tipoRecibido,
        ['success', 'danger', 'warning', 'info'],
        true
    ) ? $tipoRecibido : 'info';
}

// Procesar formulario antes de generar HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validar token CSRF
    if (!csrfValido($_POST['csrf_token'] ?? '')) {
        $flashMsg = 'La sesión del formulario venció. Intentá nuevamente.';
        $tipoMensaje = 'danger';

        header(
            'Location: ' . BASE_URL .
            '/Pages/Admin/aerolineas/index.php?mensaje=' .
            urlencode($flashMsg) .
            '&tipo=' .
            urlencode($tipoMensaje)
        );
        exit;
    }

    // Obtener datos enviados
    $codigoIATA = trim($_POST['codigoIATA'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $pais = trim($_POST['pais'] ?? '');

    // Conservar los datos para volver a mostrarlos si hay errores
    $codigoIATAOld = $codigoIATA;
    $nombreOld = $nombre;
    $paisOld = $pais;

    // Validar código IATA: exactamente tres letras
    if (!preg_match('/^[A-Za-z]{3}$/', $codigoIATA)) {
        $errores[] = 'El código IATA debe tener exactamente 3 caracteres alfabéticos.';
    } else {
        $codigoIATA = strtoupper($codigoIATA);
        $codigoIATAOld = $codigoIATA;
    }

    // Validar nombre
    if ($nombre === '') {
        $errores[] = 'El nombre de la aerolínea es obligatorio.';
    } elseif (strlen($nombre) > 120) {
        $errores[] = 'El nombre no puede tener más de 120 caracteres.';
    }

    // Validar país
    if ($pais === '') {
        $errores[] = 'El país es obligatorio.';
    }

    // Revisar duplicados e insertar si todo es válido
    if (empty($errores)) {
        try {
            $conexion = conectarDB();

            // Comprobar que el código IATA y el nombre sean únicos
            $sqlCheck = "
                SELECT codigoIATA, nombre
                FROM aerolineas
                WHERE codigoIATA = :codigoIATA
                   OR nombre = :nombre
            ";

            $checkStmt = $conexion->prepare($sqlCheck);

            $checkStmt->execute([
                'codigoIATA' => $codigoIATA,
                'nombre' => $nombre
            ]);

            $aerolineasExistentes = $checkStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($aerolineasExistentes as $aerolinea) {

                // Comprobar código IATA duplicado
                if (
                    strtoupper((string) $aerolinea['codigoIATA']) === $codigoIATA
                    && !in_array(
                        'El código IATA ya está en uso.',
                        $errores,
                        true
                    )
                ) {
                    $errores[] = 'El código IATA ya está en uso.';
                }

                // Comprobar nombre duplicado
                if (
                    strcasecmp((string) $aerolinea['nombre'], $nombre) === 0
                    && !in_array(
                        'El nombre de aerolínea ya está en uso.',
                        $errores,
                        true
                    )
                ) {
                    $errores[] = 'El nombre de aerolínea ya está en uso.';
                }
            }

            // Insertar únicamente si no hay errores
            if (empty($errores)) {
                $sqlInsert = "
                    INSERT INTO aerolineas (codigoIATA, nombre, pais)
                    VALUES (:codigoIATA, :nombre, :pais)
                ";

                $insStmt = $conexion->prepare($sqlInsert);

                $insStmt->execute([
                    'codigoIATA' => $codigoIATA,
                    'nombre' => $nombre,
                    'pais' => $pais
                ]);

                // Redirigir al listado después de guardar
                $flashMsg = 'Aerolínea creada exitosamente.';

                header(
                    'Location: ' . BASE_URL .
                    '/Pages/Admin/aerolineas/index.php?mensaje=' .
                    urlencode($flashMsg) .
                    '&tipo=success'
                );
                exit;
            }

        } catch (PDOException $e) {
            // Registrar el detalle técnico sin mostrar información sensible
            error_log('Error al crear aerolínea: ' . $e->getMessage());

            if ($e->getCode() === '23000') {
                $errores[] = 'El código IATA o el nombre de aerolínea ya está en uso.';
            } else {
                $errores[] = 'No se pudo guardar la aerolínea. Intentá nuevamente.';
            }
        }
    }
}

// IMPORTANTE: incluir el encabezado después de procesar el formulario.
// Así las redirecciones anteriores pueden ejecutarse correctamente.
require_once __DIR__ . '/../../Includes/header.php';
?>

<main class="py-5">
    <div class="container">

        <h1 class="section-title text-center mb-4">
            Crear Aerolínea
        </h1>

        <div class="mx-auto" style="max-width: 560px;">

            <?php if ($mensaje !== ''): ?>
                <div
                    class="alert alert-<?= htmlspecialchars($flashTipo, ENT_QUOTES, 'UTF-8') ?>"
                    role="status"
                >
                    <?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errores)): ?>
                <div class="alert alert-danger" role="alert">
                    <p class="fw-bold mb-2">
                        No se pudo crear la aerolínea:
                    </p>

                    <ul class="mb-0">
                        <?php foreach ($errores as $error): ?>
                            <li>
                                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form
                method="post"
                action=""
                class="volara-card p-4"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>"
                >

                <!-- Código IATA -->
                <div class="form-group mb-3">
                    <label for="codigoIATA" class="volara-label">
                        Código IATA <span class="text-danger">*</span>
                    </label>

                    <input
                        type="text"
                        name="codigoIATA"
                        id="codigoIATA"
                        class="volara-input"
                        maxlength="3"
                        minlength="3"
                        pattern="[A-Za-z]{3}"
                        title="Ingresá exactamente 3 letras."
                        value="<?= htmlspecialchars($codigoIATAOld, ENT_QUOTES, 'UTF-8') ?>"
                        autocomplete="off"
                        autocapitalize="characters"
                        required
                    >

                    <small class="form-text text-muted">
                        Debe tener exactamente 3 letras, por ejemplo: VOL.
                    </small>
                </div>

                <!-- Nombre -->
                <div class="form-group mb-3">
                    <label for="nombre" class="volara-label">
                        Nombre <span class="text-danger">*</span>
                    </label>

                    <input
                        type="text"
                        name="nombre"
                        id="nombre"
                        class="volara-input"
                        maxlength="120"
                        value="<?= htmlspecialchars($nombreOld, ENT_QUOTES, 'UTF-8') ?>"
                        autocomplete="off"
                        required
                    >

                    <small class="form-text text-muted">
                        Ingresá el nombre de la aerolínea.
                    </small>
                </div>

                <!-- País -->
                <div class="form-group mb-3">
                    <label for="pais" class="volara-label">
                        País <span class="text-danger">*</span>
                    </label>

                    <input
                        type="text"
                        name="pais"
                        id="pais"
                        class="volara-input"
                        value="<?= htmlspecialchars($paisOld, ENT_QUOTES, 'UTF-8') ?>"
                        autocomplete="country-name"
                        required
                    >

                    <small class="form-text text-muted">
                        Ingresá el país de origen de la aerolínea.
                    </small>
                </div>

                <!-- Botones -->
                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-volara">
                        <i class="bi bi-check me-2" aria-hidden="true"></i>
                        Crear
                    </button>

                    <a
                        href="<?= BASE_URL ?>/Pages/Admin/aerolineas/index.php"
                        class="btn btn-volara-outline ms-2"
                    >
                        <i class="bi bi-x me-2" aria-hidden="true"></i>
                        Cancelar
                    </a>
                </div>

            </form>

        </div>
    </div>
</main>