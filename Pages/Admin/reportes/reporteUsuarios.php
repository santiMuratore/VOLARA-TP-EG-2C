<?php
require_once __DIR__ . '/../../../Includes/functions.php';
requerirRol('administrador');

$conexion = conectarDB();

$roles = ['administrador', 'ceo aerolínea', 'usuario'];
$estados = ['pendiente de validación', 'pendiente de autorización', 'activo', 'rechazado'];

$errores = [];
$rol = $_GET['rol'] ?? '';
$estado = $_GET['estado'] ?? '';

if ($rol !== '' && !in_array($rol, $roles, true)) {
    $errores[] = 'El rol elegido no es válido.';
}
if ($estado !== '' && !in_array($estado, $estados, true)) {
    $errores[] = 'El estado elegido no es válido.';
}

$resumen = [];
$usuarios = [];

if (empty($errores)) {
    $where = ['1 = 1'];
    $params = [];

    if ($rol !== '') {
        $where[] = 'u.tipoUsuario = :rol';
        $params['rol'] = $rol;
    }
    if ($estado !== '') {
        $where[] = 'u.estadoUsuario = :estado';
        $params['estado'] = $estado;
    }
    $condiciones = implode(' AND ', $where);

    $consulta = $conexion->prepare(
        "SELECT u.estadoUsuario, COUNT(*) AS cantidad
         FROM usuarios u
         WHERE $condiciones
         GROUP BY u.estadoUsuario"
    );
    $consulta->execute($params);
    $resumen = $consulta->fetchAll(PDO::FETCH_ASSOC);

    $consulta = $conexion->prepare(
        "SELECT u.codUsuario, u.nombre, u.apellido, u.mail, u.telefono,
                u.tipoUsuario, u.estadoUsuario, u.fechaCreacion,
                a.nombre AS aerolinea
         FROM usuarios u
         LEFT JOIN aerolineas a ON a.codAerolinea = u.codAerolinea
         WHERE $condiciones
         ORDER BY u.apellido, u.nombre"
    );
    $consulta->execute($params);
    $usuarios = $consulta->fetchAll(PDO::FETCH_ASSOC);
}

$clasesEstadoUsuario = [
    'activo'                    => 'badge-approved',
    'pendiente de validación'   => 'badge-pending',
    'pendiente de autorización' => 'badge-pending',
    'rechazado'                 => 'badge-denied',
];

$titulo = 'Reporte de usuarios';
require_once __DIR__ . '/../../../Includes/header.php';
require_once __DIR__ . '/../../../Includes/navbar.php';
?>

<main class="container my-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h1 class="mb-0">Reporte de usuarios</h1>
        <button type="button" class="btn-volara-outline d-print-none" onclick="window.print()">
            <i class="bi bi-printer" aria-hidden="true"></i> Imprimir / Guardar PDF
        </button>
    </div>
    <p class="d-none d-print-block">Emitido el <?= date('d/m/Y H:i') ?></p>

    <form method="get" class="search-box mb-4 d-print-none">
        <div class="search-fields">
            <div class="form-group">
                <label for="rol" class="volara-label">Rol</label>
                <select class="volara-select" id="rol" name="rol">
                    <option value="">Todos</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= htmlspecialchars($r) ?>" <?= $rol === $r ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucfirst($r)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="estado" class="volara-label">Estado</label>
                <select class="volara-select" id="estado" name="estado">
                    <option value="">Todos</option>
                    <?php foreach ($estados as $e): ?>
                        <option value="<?= htmlspecialchars($e) ?>" <?= $estado === $e ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucfirst($e)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="search-btn-wrap gap-2">
                <button type="submit" class="btn-volara">Filtrar</button>
                <a href="reporteUsuarios.php" class="btn-volara-outline">Limpiar</a>
            </div>
        </div>
    </form>

    <?php foreach ($errores as $error): ?>
        <div class="volara-alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endforeach; ?>

    <?php if (!empty($resumen)): ?>
        <div class="d-flex flex-wrap gap-2 mb-4">
            <span class="volara-badge badge-neutral">Total: <?= count($usuarios) ?></span>
            <?php foreach ($resumen as $fila): ?>
                <span class="volara-badge <?= $clasesEstadoUsuario[$fila['estadoUsuario']] ?? 'badge-neutral' ?>">
                    <?= htmlspecialchars($fila['estadoUsuario']) ?>: <?= (int) $fila['cantidad'] ?>
                </span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($usuarios)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="bi bi-people" aria-hidden="true"></i></div>
            <p>No hay usuarios para los filtros elegidos.</p>
        </div>
    <?php else: ?>
        <div class="volara-table-responsive">
            <table class="volara-table">
                <thead>
                    <tr>
                        <th scope="col">Apellido y nombre</th>
                        <th scope="col">Mail</th>
                        <th scope="col">Teléfono</th>
                        <th scope="col">Rol</th>
                        <th scope="col">Aerolínea</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Alta</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $usuario): ?>
                        <tr>
                            <td><?= htmlspecialchars($usuario['apellido'] . ', ' . $usuario['nombre']) ?></td>
                            <td><?= htmlspecialchars($usuario['mail']) ?></td>
                            <td><?= htmlspecialchars($usuario['telefono'] ?? '') ?></td>
                            <td><?= htmlspecialchars(ucfirst($usuario['tipoUsuario'])) ?></td>
                            <td><?= htmlspecialchars($usuario['aerolinea'] ?? '—') ?></td>
                            <td>
                                <span class="volara-badge <?= $clasesEstadoUsuario[$usuario['estadoUsuario']] ?? 'badge-neutral' ?>">
                                    <?= htmlspecialchars($usuario['estadoUsuario']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars(substr($usuario['fechaCreacion'], 0, 10)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../../../Includes/footer.php'; ?>