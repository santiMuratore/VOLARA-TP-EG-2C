<?php
require_once __DIR__ . '/../../Includes/functions.php';

// Control de acceso: solo administradores
requerirRol('administrador');

$titulo = 'Gestión de Novedades';
$errores = [];
$mensaje = '';

// Flash message
if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
} else {
    $flashTipo = 'info';
}

// Procesar eliminación
if (isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
    $codNovedad = (int)$_POST['codNovedad'];

    // Verificar token CSRF
    if (!csrfValido($_POST['csrf_token'] ?? '')) {
        $mensaje = 'Error de token CSRF';
        header("Location: " . BASE_URL . "/Pages/Admin/novedades/index.php?mensaje=" . urlencode($mensaje) . "&tipo=danger");
        exit;
    } else {
        $conexion = conectarDB();
        $sqlDelete = "DELETE FROM novedades WHERE codNovedad = :codNovedad";
        $delStmt = $conexion->prepare($sqlDelete);
        $delStmt->execute(['codNovedad' => $codNovedad]);

        if ($delStmt->rowCount() > 0) {
            $mensaje = 'Novedad eliminada exitosamente';
            header("Location: " . BASE_URL . "/Pages/Admin/novedades/index.php?mensaje=" . urlencode($mensaje) . "&tipo=success");
            exit;
        } else {
            $mensaje = 'Error al eliminar la novedad';
            header("Location: " . BASE_URL . "/Pages/Admin/novedades/index.php?mensaje=" . urlencode($mensaje) . "&tipo=danger");
            exit;
        }
    }
}

// Paginación
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$porPagina = 10;

$conexion = conectarDB();

// Contar total
$sqlCount = "SELECT COUNT(*) as total FROM novedades";
$countStmt = $conexion->query($sqlCount);
$total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$paginacion = paginar($total, $porPagina, $pagina);
$offset = $paginacion['offset'];

// Consultar novedades con bindValue
$sql = "SELECT codNovedad, titulo, texto, fechaPublicacion, fechaExpiracion FROM novedades ORDER BY fechaPublicacion DESC LIMIT :offset, :porPagina";
$stmt = $conexion->prepare($sql);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':porPagina', $porPagina, PDO::PARAM_INT);
$stmt->execute();
$novedades = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generar paginación HTML
$paginationHTML = generarPaginacionHTML($total, $porPagina, $pagina, BASE_URL . "/Pages/Admin/novedades/index.php");
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
        <div class="row">
            <div class="col-12">
                <h1 class="section-title">Gestión de Novedades</h1>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <?php if ($mensaje): ?>
                    <div class="alert alert-<?= $flashTipo ?> alert-dismissible fade show mb-3" role="alert">
                        <?= $mensaje ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-12">
                        <?php if (count($novedades) > 0): ?>
                            <table class="volara-table">
                                <thead>
                                    <tr>
                                        <th>Título</th>
                                        <th>Fecha Publicación</th>
                                        <th>Fecha Expiración</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($novedades as $n): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($n['titulo']) ?></td>
                                            <td><?= htmlspecialchars($n['fechaPublicacion']) ?></td>
                                            <td><?= htmlspecialchars($n['fechaExpiracion']) ?></td>
                                            <td class="text-end">
                                                <a href="<?= BASE_URL ?>/Pages/Admin/novedades/editar.php?cod=<?= $n['codNovedad'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                                                <form method="post" style="display:inline" onsubmit="return confirm('¿Seguro que desea eliminar esta novedad?');">
                                                    <input type="hidden" name="codNovedad" value="<?= $n['codNovedad'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                                    <button type="submit" name="accion" value="eliminar" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-state text-center py-5">
                                <i class="bi bi-file-text me-2" style="font-size: 3rem;"></i>
                                <h3>No hay novedades registradas</h3>
                                <p>Sé el primero en agregar una novedad.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Paginación inferior -->
<?= $paginationHTML ?>

<?php require __DIR__ . '/../../Includes/footer.php'; ?>
</body>
</html>