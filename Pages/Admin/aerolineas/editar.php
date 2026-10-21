<?php
require_once __DIR__ . '/../../Includes/functions.php';

// Control de acceso: solo administradores
requerirRol('administrador');

$titulo = 'Editar Aerolínea';
$errores = [];
$mensaje = '';

// Datos del formulario
$codigoIATAOld = '';
$nombreOld = '';
$paisOld = '';
$codAerolineaOld = 0;

// Obtener datos de la aerolínea a editar
if (isset($_GET['cod']) && is_numeric($_GET['cod'])) {
    $codAerolineaOld = (int)$_GET['cod'];
    $conexion = conectarDB();
    $sql = "SELECT codigoIATA, nombre, pais FROM aerolineas WHERE codAerolinea = :codAerolinea";
    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':codAerolinea', $codAerolineaOld, PDO::PARAM_INT);
    $stmt->execute();
    $aerolinea = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($aerolinea) {
        $codigoIATAOld = $aerolinea['codigoIATA'];
        $nombreOld = $aerolinea['nombre'];
        $paisOld = $aerolinea['pais'];
    } else {
        // Aerolínea no encontrada, redirigir
        header("Location: " . BASE_URL . "/Pages/Admin/aerolineas/index.php");
        exit;
    }
} else {
    // ID inválido
    header("Location: " . BASE_URL . "/Pages/Admin/aerolineas/index.php");
    exit;
}

// Flash message si existe
if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $flashTipo = $_GET['tipo'] ?? 'info';
} else {
    $flashTipo = 'info';
}

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar token CSRF
    if (!csrfValido($_POST['csrf_token'] ?? '')) {
        $mensaje = 'Error de token CSRF';
        header("Location: " . BASE_URL . "/Pages/Admin/aerolineas/index.php?mensaje=" . urlencode($mensaje) . "&tipo=danger");
        exit;
    }
    $codigoIATA = trim($_POST['codigoIATA'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $pais = trim($_POST['pais'] ?? '');

    // Reiniciar errores
    $errores = [];

    // Validación: Código IATA
    if (!preg_match('/^[A-Za-z]{3}$/', $codigoIATA)) {
        $errores[] = 'El código IATA debe tener exactamente 3 caracteres alfabéticos';
    } else {
        $codigoIATA = strtoupper($codigoIATA);
    }

    // Nombre obligatorio
    if (empty($nombre)) {
        $errores[] = 'El nombre es obligatorio';
    } elseif (strlen($nombre) > 120) {
        $errores[] = 'El nombre no puede tener más de 120 caracteres';
    }

    // País obligatorio
    if (empty($pais)) {
        $errores[] = 'El país es obligatorio';
    }

    // Validar unicidad (excluyendo la aerolínea actual)
    if (empty($errores)) {
        try {
            $conexion = conectarDB();
            $sqlCheck = "SELECT codigoIATA, nombre FROM aerolineas WHERE (codigoIATA = :codigoIATA OR nombre = :nombre) AND codAerolinea <> :codAerolineaActual";
            $checkStmt = $conexion->prepare($sqlCheck);
            $checkStmt->execute(['codigoIATA' => $codigoIATA, 'nombre' => $nombre, 'codAerolineaActual' => $codAerolineaOld]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                if ($existing['codigoIATA'] === $codigoIATA) {
                    $errores[] = 'El código IATA ya está en uso por otra aerolínea';
                }
                if ($existing['nombre'] === $nombre) {
                    $errores[] = 'El nombre de aerolínea ya está en uso por otra aerolínea';
                }
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $errores[] = 'Error de integridad de datos: verifique que el IATA y nombre sean únicos';
            } else {
                $errores[] = 'Error en la base de datos';
            }
        }
    }

    // Si no hay errores, actualizar
    if (empty($errores)) {
        $conexion = conectarDB();
        $sqlUpdate = "UPDATE aerolineas SET codigoIATA = :codigoIATA, nombre = :nombre, pais = :pais WHERE codAerolinea = :codAerolinea";
        $upStmt = $conexion->prepare($sqlUpdate);
        $result = $upStmt->execute(['codigoIATA' => $codigoIATA, 'nombre' => $nombre, 'pais' => $pais, 'codAerolinea' => $codAerolineaOld]);

        if ($result) {
            $flashMsg = 'Aerolínea actualizada exitosamente';
            header("Location: " . BASE_URL . "/Pages/Admin/aerolineas/index.php?mensaje=" . urlencode($flashMsg) . "&tipo=success");
            exit;
        }
        $errores[] = 'Error al actualizar la aerolínea';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo) ?> | Volara</title>
</head>
<body>
<?php require __DIR__ . '/../../Includes/header.php'; ?>

<main class="py-5">
    <div class="container">
        <h1 class="section-title text-center mb-4">Editar Aerolínea</h1>

        <form method="post" action="" class="volara-card p-4 max-w-md">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="codAerolinea" value="<?= $codAerolineaOld ?>">

            <div class="form-group mb-3">
                <label for="codigoIATA" class="volara-label">Código IATA <span class="text-danger">*</span></label>
                <input type="text" name="codigoIATA" id="codigoIATA" class="volara-input" maxlength="3" required
                    pattern="[A-Za-z]{3}"
                    title="3 caracteres alfabéticos"
                    value="<?= htmlspecialchars($codigoIATAOld) ?>"
                    autocomplete="off">
                <small class="form-error">Debe tener exactamente 3 caracteres alfabéticos</small>
            </div>

            <div class="form-group mb-3">
                <label for="nombre" class="volara-label">Nombre <span class="text-danger">*</span></label>
                <input type="text" name="nombre" id="nombre" class="volara-input" maxlength="120" required
                    value="<?= htmlspecialchars($nombreOld) ?>"
                    autocomplete="off">
                <small class="form-error">Nombre obligatorio, máximo 120 caracteres</small>
            </div>

            <div class="form-group mb-3">
                <label for="pais" class="volara-label">País <span class="text-danger">*</span></label>
                <input type="text" name="pais" id="pais" class="volara-input" required
                    value="<?= htmlspecialchars($paisOld) ?>"
                    autocomplete="off">
                <small class="form-error">El país es obligatorio</small>
            </div>

            <div class="text-center mt-4">
                <button type="submit" class="btn btn-volara">
                    <i class="bi bi-check me-2"></i> Actualizar
                </button>
                <a href="<?= BASE_URL ?>/Pages/Admin/aerolineas/index.php" class="btn btn-volara-outline ms-2">
                    <i class="bi bi-x me-2"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</main>

<?php require __DIR__ . '/../../Includes/footer.php'; ?>
</body>
</html>