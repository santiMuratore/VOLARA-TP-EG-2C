<?php
/**
 * Edición de aerolíneas - VOLARA.
 * Ubicación: Pages/Admin/aerolineas/editar.php
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../../Includes/functions.php';
require_once __DIR__ . '/../../../Includes/functionsF2.php';

$baseUrl = rtrim(BASE_URL, '/');
$urlListado = $baseUrl . '/Pages/Admin/aerolineas/index.php';
$escapar = static fn($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');

/* --------------------------------------------------------------------------
 * Autenticación y autorización.
 * -------------------------------------------------------------------------- */
$usuarioActual = [];
if (!empty($_SESSION['codUsuario']) && function_exists('usuarioActual')) {
    $datosUsuario = usuarioActual();
    if (is_array($datosUsuario)) {
        $usuarioActual = $datosUsuario;
    }
}

$rol = trim((string) (
    $usuarioActual['rol']
    ?? $usuarioActual['tipoUsuario']
    ?? $_SESSION['tipoUsuario']
    ?? ''
));
$rol = function_exists('mb_strtolower') ? mb_strtolower($rol, 'UTF-8') : strtolower($rol);
$rol = strtr($rol, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']);

if (empty($_SESSION['codUsuario'])) {
    header('Location: ' . $baseUrl . '/Auth/login.php');
    exit;
}
if (!in_array($rol, ['admin', 'administrador'], true)) {
    header('Location: ' . $baseUrl . '/Pages/Publico/index.php');
    exit;
}

$titulo = 'Editar aerolínea';
$pageDescription = 'Actualizá los datos de una aerolínea registrada en VOLARA.';
$errores = [];
$codigoIATA = '';
$nombre = '';
$pais = '';

/* --------------------------------------------------------------------------
 * Validar ID y recuperar la aerolínea.
 * -------------------------------------------------------------------------- */
$idFuente = $_GET['cod'] ?? $_POST['codAerolinea'] ?? null;
$codAerolinea = filter_var(
    $idFuente,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($codAerolinea === false || $codAerolinea === null) {
    header(
        'Location: ' . $urlListado
        . '?mensaje=' . rawurlencode('La aerolínea seleccionada no es válida.')
        . '&tipo=danger'
    );
    exit;
}

try {
    $conexion = conectarDB();
    $stmtBuscar = $conexion->prepare(
        'SELECT codAerolinea, codigoIATA, nombre, pais
         FROM aerolineas
         WHERE codAerolinea = :codAerolinea'
    );
    $stmtBuscar->execute(['codAerolinea' => $codAerolinea]);
    $aerolinea = $stmtBuscar->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('VOLARA - Error al consultar aerolínea para editar: ' . $e->getMessage());
    $aerolinea = false;
    $errores[] = 'No se pudieron recuperar los datos de la aerolínea.';
}

if (!$aerolinea) {
    if (!$errores) {
        header(
            'Location: ' . $urlListado
            . '?mensaje=' . rawurlencode('La aerolínea solicitada no existe.')
            . '&tipo=warning'
        );
        exit;
    }
} else {
    $codigoIATA = (string) $aerolinea['codigoIATA'];
    $nombre = (string) $aerolinea['nombre'];
    $pais = (string) $aerolinea['pais'];
}

/* --------------------------------------------------------------------------
 * Procesar actualización.
 * -------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $aerolinea) {
    $tokenRecibido = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null;
    $codigoIATA = is_string($_POST['codigoIATA'] ?? null) ? trim($_POST['codigoIATA']) : '';
    $nombre = is_string($_POST['nombre'] ?? null) ? trim($_POST['nombre']) : '';
    $pais = is_string($_POST['pais'] ?? null) ? trim($_POST['pais']) : '';

    if (!csrfValido($tokenRecibido)) {
        $errores[] = 'La sesión del formulario venció. Recargá la página e intentá nuevamente.';
    }

    if (!preg_match('/^[A-Za-z]{3}$/', $codigoIATA)) {
        $errores[] = 'El código IATA debe tener exactamente tres letras, por ejemplo VOL.';
    } else {
        $codigoIATA = strtoupper($codigoIATA);
    }

    $longitudNombre = function_exists('mb_strlen') ? mb_strlen($nombre, 'UTF-8') : strlen($nombre);
    if ($nombre === '') {
        $errores[] = 'El nombre de la aerolínea es obligatorio.';
    } elseif ($longitudNombre > 120) {
        $errores[] = 'El nombre no puede superar los 120 caracteres.';
    }

    $longitudPais = function_exists('mb_strlen') ? mb_strlen($pais, 'UTF-8') : strlen($pais);
    if ($pais === '') {
        $errores[] = 'El país es obligatorio.';
    } elseif ($longitudPais > 100) {
        $errores[] = 'El país no puede superar los 100 caracteres.';
    }

    if (!$errores) {
        try {
            $stmtDuplicados = $conexion->prepare(
                'SELECT codigoIATA, nombre
                 FROM aerolineas
                 WHERE (UPPER(codigoIATA) = :codigoIATA
                    OR LOWER(nombre) = LOWER(:nombre))
                   AND codAerolinea <> :codAerolinea'
            );
            $stmtDuplicados->execute([
                'codigoIATA' => $codigoIATA,
                'nombre' => $nombre,
                'codAerolinea' => $codAerolinea,
            ]);
            $existentes = $stmtDuplicados->fetchAll(PDO::FETCH_ASSOC);

            foreach ($existentes as $existente) {
                if (strtoupper((string) $existente['codigoIATA']) === $codigoIATA) {
                    $errores[] = 'El código IATA ya está registrado por otra aerolínea.';
                    break;
                }
            }
            foreach ($existentes as $existente) {
                if (strcasecmp((string) $existente['nombre'], $nombre) === 0) {
                    $errores[] = 'Ese nombre ya está registrado por otra aerolínea.';
                    break;
                }
            }

            if (!$errores) {
                $stmtActualizar = $conexion->prepare(
                    'UPDATE aerolineas
                     SET codigoIATA = :codigoIATA,
                         nombre = :nombre,
                         pais = :pais
                     WHERE codAerolinea = :codAerolinea'
                );
                $stmtActualizar->execute([
                    'codigoIATA' => $codigoIATA,
                    'nombre' => $nombre,
                    'pais' => $pais,
                    'codAerolinea' => $codAerolinea,
                ]);

                header(
                    'Location: ' . $urlListado
                    . '?mensaje=' . rawurlencode('Aerolínea actualizada correctamente.')
                    . '&tipo=success'
                );
                exit;
            }
        } catch (PDOException $e) {
            error_log('VOLARA - Error al editar aerolínea: ' . $e->getMessage());
            if ((string) $e->getCode() === '23000') {
                $errores[] = 'El código IATA o el nombre ya está registrado.';
            } else {
                $errores[] = 'No se pudieron guardar los cambios. Intentá nuevamente.';
            }
        }
    }
}

require_once __DIR__ . '/../../../Includes/header.php';
require_once __DIR__ . '/../../../Includes/navbar.php';
?>

<main id="contenido-principal" class="py-5" tabindex="-1">
    <section class="page-header">
        <div class="container">
            <h1>Editar aerolínea</h1>
            <p class="text-muted mb-3">Actualizá los datos de la aerolínea seleccionada.</p>
            <nav aria-label="Ruta de navegación">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= $escapar($baseUrl . '/Pages/Admin/index.php') ?>">Panel de administración</a></li>
                    <li class="breadcrumb-item"><a href="<?= $escapar($urlListado) ?>">Aerolíneas</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Editar</li>
                </ol>
            </nav>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="mx-auto" style="max-width: 640px;">
                <?php if ($errores): ?>
                    <div class="volara-alert alert-danger mb-4" role="alert">
                        <strong>No se pudieron guardar los cambios:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach (array_unique($errores) as $error): ?>
                                <li><?= $escapar($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($aerolinea): ?>
                    <div class="volara-card p-4 p-md-5">
                        <form method="post" action="<?= $escapar($baseUrl . '/Pages/Admin/aerolineas/editar.php?cod=' . $codAerolinea) ?>">
                            <input type="hidden" name="csrf_token" value="<?= $escapar(csrfToken()) ?>">
                            <input type="hidden" name="codAerolinea" value="<?= (int) $codAerolinea ?>">

                            <div class="form-group mb-4">
                                <label for="codigoIATA" class="volara-label">Código IATA <span class="text-danger">*</span></label>
                                <input
                                    type="text"
                                    name="codigoIATA"
                                    id="codigoIATA"
                                    class="volara-input"
                                    minlength="3"
                                    maxlength="3"
                                    pattern="[A-Za-z]{3}"
                                    title="Ingresá exactamente tres letras."
                                    value="<?= $escapar($codigoIATA) ?>"
                                    autocapitalize="characters"
                                    autocomplete="off"
                                    required
                                >
                                <small class="text-muted">Exactamente tres letras.</small>
                            </div>

                            <div class="form-group mb-4">
                                <label for="nombre" class="volara-label">Nombre de la aerolínea <span class="text-danger">*</span></label>
                                <input
                                    type="text"
                                    name="nombre"
                                    id="nombre"
                                    class="volara-input"
                                    maxlength="120"
                                    value="<?= $escapar($nombre) ?>"
                                    autocomplete="organization"
                                    required
                                >
                            </div>

                            <div class="form-group mb-4">
                                <label for="pais" class="volara-label">País <span class="text-danger">*</span></label>
                                <input
                                    type="text"
                                    name="pais"
                                    id="pais"
                                    class="volara-input"
                                    maxlength="100"
                                    value="<?= $escapar($pais) ?>"
                                    autocomplete="country-name"
                                    required
                                >
                            </div>

                            <p class="small text-muted">Los campos marcados con <span class="text-danger">*</span> son obligatorios.</p>

                            <div class="d-flex flex-column flex-sm-row gap-2 mt-4">
                                <button type="submit" class="btn btn-volara">
                                    <i class="bi bi-check-lg me-2" aria-hidden="true"></i>Guardar cambios
                                </button>
                                <a href="<?= $escapar($urlListado) ?>" class="btn btn-volara-outline">
                                    <i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Cancelar
                                </a>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../../../Includes/footer.php'; ?>
