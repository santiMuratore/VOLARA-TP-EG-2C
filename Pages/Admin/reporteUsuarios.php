<?php
require_once __DIR__ . '/../Includes/functions.php';
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

$titulo = 'Reporte de usuarios';
require_once __DIR__ . '/../Includes/header.php';
?>

<main class="container my-4">
    <div class="d-flex justify-content-between align-items-center">
        <h1>Reporte de usuarios</h1>
        <button type="button" class="btn btn-outline-secondary d-print-none"
                onclick="window.print()">Imprimir / Guardar PDF</button>
    </div>
    <p class="d-none d-print-block">Emitido el <?= date('d/m/Y H:i') ?></p>

    <form method="get" class="row g-3 align-items-end mb-4 d-print-none">
        <div class="col-md-4">
            <label for="rol" class="form-label">Rol</label>
            <select class="form-select" id="rol" name="rol">
                <option value="">Todos</option>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= htmlspecialchars($r) ?>" <?= $rol === $r ? 'selected' : '' ?>>
                        <?= htmlspecialchars(ucfirst($r)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label for="estado" class="form-label">Estado</label>
            <select class="form-select" id="estado" name="estado">
                <option value="">Todos</option>
                <?php foreach ($estados as $e): ?>
                    <option value="<?= htmlspecialchars($e) ?>" <?= $estado === $e ? 'selected' : '' ?>>
                        <?= htmlspecialchars(ucfirst($e)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <a href="reporteUsuarios.php" class="btn btn-secondary">Limpiar</a>
        </div>
    </form>

    <?php foreach ($errores as $error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endforeach; ?>

    <?php if (!empty($resumen)): ?>
        <p>
            <strong>Total:</strong> <?= count($usuarios) ?>
            <?php foreach ($resumen as $fila): ?>
                | <?= htmlspecialchars(ucfirst($fila['estadoUsuario'])) ?>: <?= (int) $fila['cantidad'] ?>
            <?php endforeach; ?>
        </p>
    <?php endif; ?>

    <?php if (empty($usuarios)): ?>
        <div class="alert alert-info">No hay usuarios para los filtros elegidos.</div>
    <?php else: ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Apellido y nombre</th>
                    <th>Mail</th>
                    <th>Teléfono</th>
                    <th>Rol</th>
                    <th>Aerolínea</th>
                    <th>Estado</th>
                    <th>Alta</th>
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
                        <td><?= htmlspecialchars(ucfirst($usuario['estadoUsuario'])) ?></td>
                        <td><?= htmlspecialchars(substr($usuario['fechaCreacion'], 0, 10)) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../Includes/footer.php'; ?>