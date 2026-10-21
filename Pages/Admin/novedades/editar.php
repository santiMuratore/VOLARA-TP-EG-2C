<?php
require_once __DIR__ . '/../../../Includes/functions.php';
require_once __DIR__ . '/../../../Includes/functionsF2.php';

// Control de acceso: solo administradores
requerirRol('administrador');

$titulo = 'Editar Novedad';
$errores = [];
$mensaje = '';

// Datos del formulario
$codNovedadOld = 0;
$tituloOld = '';
$textoOld = '';
$fechaPubOld = '';
$fechaExpOld = '';

// Obtener datos de la novedad a editar
if (isset($_GET['cod']) && is_numeric($_GET['cod'])) {
    $codNovedadOld = (int)$_GET['cod'];
    $conexion = conectarDB();
    $sql = "SELECT titulo, texto, fechaPublicacion, fechaExpiracion FROM novedades WHERE codNovedad = :codNovedad";
    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(':codNovedad', $codNovedadOld, PDO::PARAM_INT);
    $stmt->execute();
    $novedad = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($novedad) {
        $tituloOld = $novedad['titulo'];
        $textoOld = $novedad['texto'];
        $fechaPubOld = $novedad['fechaPublicacion'];
        $fechaExpOld = $novedad['fechaExpiracion'];
    } else {
        // Novedad no encontrada
        header("Location: " . BASE_URL . "/Pages/Admin/novedades/index.php");
        exit;
    }
} else {
    // ID inválido
    header("Location: " . BASE_URL . "/Pages/Admin/novedades/index.php");
    exit;
}

// Flash message
if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
} else {
    $flashTipo = 'info';
}

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $texto = trim($_POST['texto'] ?? '');
    $fechaPub = trim($_POST['fechaPublicacion'] ?? '');
    $fechaExp = trim($_POST['fechaExpiracion'] ?? '');

    $errores = [];

    // Validación: Título
    if (empty($titulo)) {
        $errores[] = 'El título es obligatorio';
    } elseif (strlen($titulo) > 200) {
        $errores[] = 'El título no puede tener más de 200 caracteres';
    }

    // Validación: Texto
    if (empty($texto)) {
        $errores[] = 'El texto es obligatorio';
    }

    // Validación: Fechas con DateTime::createFromFormat
    $hoy = date('Y-m-d');

    // Fecha de publicación
    if (!empty($fechaPub)) {
        $datePub = DateTime::createFromFormat('Y-m-d', $fechaPub);
        if (!$datePub || $datePub->format('Y-m-d') !== $fechaPub) {
            $errores[] = 'Formato de fecha de publicación inválido';
        }
    } else {
        $errores[] = 'La fecha de publicación es obligatoria';
    }

    // Fecha de expiración
    if (!empty($fechaExp)) {
        $dateExp = DateTime::createFromFormat('Y-m-d', $fechaExp);
        if (!$dateExp || $dateExp->format('Y-m-d') !== $fechaExp) {
            $errores[] = 'Formato de fecha de expiración inválido';
        }
        // Validar que expiración sea posterior a publicación
        if (!empty($fechaPub) && $datePub && $dateExp) {
            if ($dateExp <= $datePub) {
                $errores[] = 'La fecha de expiración debe ser posterior a la fecha de publicación';
            }
        }
    } else {
        $errores[] = 'La fecha de expiración es obligatoria';
    }

    // Si no hay errores, actualizar
    if (empty($errores)) {
        $conexion = conectarDB();
        $sqlUpdate = "UPDATE novedades SET titulo = :titulo, texto = :texto, fechaPublicacion = :fechaPub, fechaExpiracion = :fechaExp WHERE codNovedad = :codNovedad";
        $upStmt = $conexion->prepare($sqlUpdate);
        $result = $upStmt->execute(['titulo' => $titulo, 'texto' => $texto, 'fechaPub' => $fechaPub, 'fechaExp' => $fechaExp, 'codNovedad' => $codNovedadOld]);

        if ($result) {
            $flashMsg = 'Novedad actualizada exitosamente';
            header("Location: " . BASE_URL . "/Pages/Admin/novedades/index.php?mensaje=" . urlencode($flashMsg) . "&tipo=success");
            exit;
        }
        $errores[] = 'Error al actualizar la novedad';
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
<main class="py-5">
    <div class="container">
        <h1 class="section-title text-center mb-4">Editar Novedad</h1>

        <form method="post" action="" class="volara-card p-4 max-w-md">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="codNovedad" value="<?= $codNovedadOld ?>">

            <div class="form-group mb-3">
                <label for="titulo" class="volara-label">Título <span class="text-danger">*</span></label>
                <input type="text" name="titulo" id="titulo" class="volara-input" maxlength="200" required
                    value="<?= htmlspecialchars($tituloOld) ?>"
                    autocomplete="off">
                <small class="form-error">Título obligatorio, máximo 200 caracteres</small>
            </div>

            <div class="form-group mb-3">
                <label for="texto" class="volara-label">Texto <span class="text-danger">*</span></label>
                <textarea name="texto" id="texto" class="volara-input" rows="3" maxlength="65535" required
                    autocomplete="off"><?= htmlspecialchars($textoOld) ?></textarea>
                <small class="form-error">Texto obligatorio</small>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-6">
                    <label for="fechaPublicacion" class="volara-label">Fecha de Publicación <span class="text-danger">*</span></label>
                    <input type="date" name="fechaPublicacion" id="fechaPublicacion" class="volara-input" required
                        value="<?= htmlspecialchars($fechaPubOld) ?>">
                    <small class="form-error">Formato: YYYY-MM-DD</small>
                </div>
                <div class="col-6">
                    <label for="fechaExpiracion" class="volara-label">Fecha de Expiración <span class="text-danger">*</span></label>
                    <input type="date" name="fechaExpiracion" id="fechaExpiracion" class="volara-input" required
                        value="<?= htmlspecialchars($fechaExpOld) ?>">
                    <small class="form-error">Debe ser posterior a la fecha de publicación</small>
                </div>
            </div>

            <div class="text-center mt-4">
                <button type="submit" class="btn btn-volara">
                    <i class="bi bi-check me-2"></i> Actualizar
                </button>
                <a href="<?= BASE_URL ?>/Pages/Admin/novedades/index.php" class="btn btn-volara-outline ms-2">
                    <i class="bi bi-x me-2"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</main>


</body>
</html>