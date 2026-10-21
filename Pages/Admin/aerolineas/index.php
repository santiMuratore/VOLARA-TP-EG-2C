<?php
require_once __DIR__ . '/../../Includes/functions.php';

// Control de acceso: solo administradores
requerirRol('administrador');

$titulo = 'Gestión de Aerolíneas';
$errores = [];
$mensaje = '';
$tipoMensaje = 'info';

// Manejo de mensajes flash
if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $tipoMensaje = $_GET['tipo'] ?? 'info';
    unset($_SESSION['flash_mensaje'], $_SESSION['flash_tipo']);
}

if (isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
    $codAerolinea = (int)$_POST['codAerolinea'];

    // Verificar token CSRF
    if (!csrfValido($_POST['csrf_token'] ?? '')) {
        $mensaje = 'Error de token CSRF';
        $tipoMensaje = 'danger';
        header("Location: " . BASE_URL . "/Pages/Admin/aerolineas/index.php?mensaje=" . urlencode($mensaje) . "&tipo=" . urlencode($tipoMensaje));
        exit;
    } else {
        $conexion = conectarDB();
        // Verificar si tiene vuelos o CEO asociados
        $sqlCheck = "SELECT COUNT(*) as total FROM vuelos WHERE codAerolinea = :codAerolinea";
        $checkStmt = $conexion->prepare($sqlCheck);
        $checkStmt->execute(['codAerolinea' => $codAerolinea]);
        $vuelos = $checkStmt->fetch(PDO::FETCH_ASSOC)['total'];

        if ($vuelos > 0) {
            $mensaje = 'No se puede eliminar: la aerolínea tiene ' . $vuelos . ' vuelo(s) asociado(s)';
            $tipoMensaje = 'danger';
            header("Location: " . BASE_URL . "/Pages/Admin/aerolineas/index.php?mensaje=" . urlencode($mensaje) . "&tipo=" . urlencode($tipoMensaje));
            exit;
        } else {
            $sqlDelete = "DELETE FROM aerolineas WHERE codAerolinea = :codAerolinea";
            $delStmt = $conexion->prepare($sqlDelete);
            $delStmt->execute(['codAerolinea' => $codAerolinea]);
            if ($delStmt->rowCount() > 0) {
                $mensaje = 'Aerolínea eliminada exitosamente';
                $tipoMensaje = 'success';
                header("Location: " . BASE_URL . "/Pages/Admin/aerolineas/index.php?mensaje=" . urlencode($mensaje) . "&tipo=" . urlencode($tipoMensaje));
                exit;
            } else {
                $mensaje = 'Error al eliminar la aerolínea';
                $tipoMensaje = 'danger';
                header("Location: " . BASE_URL . "/Pages/Admin/aerolineas/index.php?mensaje=" . urlencode($mensaje) . "&tipo=" . urlencode($tipoMensaje));
                exit;
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigoIATA = trim($_POST['codigoIATA'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $pais = trim($_POST['pais'] ?? '');

    // Validación en servidor
    // Código IATA: exactamente 3 caracteres alfabéticos, normalizar a mayúsculas
    if (!preg_match('/^[A-Za-z]{3}$/', $codigoIATA)) {
        $errores[] = 'El código IATA debe tener exactamente 3 caracteres alfabéticos';
    } else {
        $codigoIATA = strtoupper($codigoIATA);
    }

    // Nombre obligatorio y longitud máxima
    if (empty($nombre)) {
        $errores[] = 'El nombre es obligatorio';
    } elseif (strlen($nombre) > 120) {
        $errores[] = 'El nombre no puede tener más de 120 caracteres';
    }

    // País obligatorio
    if (empty($pais)) {
        $errores[] = 'El país es obligatorio';
    }

    // Validar unicidad en servidor (capturar PDOException para código 23000)
    if (empty($errores)) {
        try {
            $conexion = conectarDB();
            $sqlCheck = "SELECT codigoIATA, nombre FROM aerolineas WHERE codigoIATA = :codigoIATA OR nombre = :nombre";
            // Si es edición, excluir la aerolínea actual
            if (isset($_POST['codAerolinea'])) {
                $sqlCheck .= " AND codAerolinea <> :codAerolineaActual";
            }
            $checkStmt = $conexion->prepare($sqlCheck);
            $checkParams = ['codigoIATA' => $codigoIATA, 'nombre' => $nombre];
            if (isset($_POST['codAerolinea'])) {
                $checkParams['codAerolineaActual'] = (int)$_POST['codAerolinea'];
            }
            $checkStmt->execute($checkParams);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                if ($existing['codigoIATA'] === $codigoIATA) {
                    $errores[] = 'El código IATA ya está en uso';
                }
                if ($existing['nombre'] === $nombre) {
                    $errores[] = 'El nombre de aerolínea ya está en uso';
                }
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $errores[] = 'Error de integridad de datos: verifique que el IATA y nombre sean únicos';
            } else {
                $errores[] = 'Error en la base de datos: ' . $e->getMessage();
            }
        }
    }

    // Si no hay errores, insertar o actualizar
    if (empty($errores)) {
        $conexion = conectarDB();
        if (isset($_POST['codAerolinea']) && $_POST['codAerolinea'] > 0) {
            // Actualizar
            $sqlUpdate = "UPDATE aerolineas SET codigoIATA = :codigoIATA, nombre = :nombre, pais = :pais WHERE codAerolinea = :codAerolinea";
            $upStmt = $conexion->prepare($sqlUpdate);
            $result = $upStmt->execute(['codigoIATA' => $codigoIATA, 'nombre' => $nombre, 'pais' => $pais, 'codAerolinea' => (int)$_POST['codAerolinea']]);
            if ($result) {
                $flashMsg = 'Aerolínea actualizada exitosamente';
                $flashTipo = 'success';
                header("Location: " . BASE_URL . "/Pages/Admin/aerolineas/index.php?mensaje=" . urlencode($flashMsg) . "&tipo=" . urlencode($flashTipo));
                exit;
            }
        } else {
            // Insertar
            $sqlInsert = "INSERT INTO aerolineas (codigoIATA, nombre, pais) VALUES (:codigoIATA, :nombre, :pais)";
            $insStmt = $conexion->prepare($sqlInsert);
            $result = $insStmt->execute(['codigoIATA' => $codigoIATA, 'nombre' => $nombre, 'pais' => $pais]);
            if ($result) {
                $flashMsg = 'Aerolínea creada exitosamente';
                $flashTipo = 'success';
                header("Location: " . BASE_URL . "/Pages/Admin/aerolineas/index.php?mensaje=" . urlencode($flashMsg) . "&tipo=" . urlencode($flashTipo));
                exit;
            }
        }
        $errores[] = 'Error al guardar la aerolínea';
    }
}

// Obtener lista paginada de aerolíneas
$conexion = conectarDB();
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$porPagina = 10;

// Contar total
$sqlCount = "SELECT COUNT(*) as total FROM aerolineas";
$countStmt = $conexion->query($sqlCount);
$total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$paginacion = paginar($total, $porPagina, $pagina);
$offset = $paginacion['offset'];

// Consultar aerolíneas usando bindValue con PARAM_INT
$sql = "SELECT codAerolinea, codigoIATA, nombre, pais FROM aerolineas ORDER BY nombre ASC LIMIT :offset, :porPagina";
$stmt = $conexion->prepare($sql);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':porPagina', $porPagina, PDO::PARAM_INT);
$stmt->execute();
$aerolineas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generar paginación HTML
$paginationHTML = generarPaginacionHTML($total, $porPagina, $pagina, BASE_URL . "/Pages/Admin/aerolineas/index.php");
?>
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
        <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipoMensaje ?> alert-dismissible fade show mb-3" role="alert">
                <?= $mensaje ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (count($errores) > 0): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                <strong>Errores encontrados:</strong>
                <ul class="mb-0">
                    <?php foreach ($errores as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="section-title">Gestión de Aerolíneas</h1>
            <a href="<?= BASE_URL ?>/Pages/Admin/aerolineas/crear.php" class="btn btn-volara">
                <i class="bi bi-plus-square me-2"></i> Crear Aerolínea
            </a>
        </div>

        <?php if (count($aerolineas) > 0): ?>
            <div class="table-responsive">
                <table class="volara-table">
                    <thead>
                        <tr>
                            <th>Código IATA</th>
                            <th>Nombre</th>
                            <th>País</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($aerolineas as $a): ?>
                            <tr>
                                <td><?= htmlspecialchars($a['codigoIATA']) ?></td>
                                <td><?= htmlspecialchars($a['nombre']) ?></td>
                                <td><?= htmlspecialchars($a['pais']) ?></td>
                                <td class="text-end">
                                    <a href="<?= BASE_URL ?>/Pages/Admin/aerolineas/editar.php?cod=<?= $a['codAerolinea'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                                    <form method="post" style="display:inline" onsubmit="return confirm('¿Seguro que desea eliminar esta aerolínea?');">
                                        <input type="hidden" name="codAerolinea" value="<?= $a['codAerolinea'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                        <button type="submit" name="accion" value="eliminar" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state text-center py-5">
                <i class="bi bi-airplane me-2" style="font-size: 3rem;"></i>
                <h3>No hay aerolíneas registradas</h3>
                <p>Sé el primero en agregar una aerolínea.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Paginación inferior -->
<?= $paginationHTML ?>

<?php require_once __DIR__ . '/../../Includes/footer.php'; ?>
</body>
</html>