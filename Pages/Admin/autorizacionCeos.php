<?php

require_once __DIR__ . '/../../Includes/functions.php';

requerirRol('administrador');

$conexion = conectarDB();

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $codUsuario = (int) ($_POST['codUsuario'] ?? 0);
    $accion = $_POST['accion'] ?? '';

    if ($codUsuario <= 0) {

        $error = 'El usuario indicado no es válido.';

    } elseif (!in_array($accion, ['autorizar', 'rechazar'], true)) {

        $error = 'La acción indicada no es válida.';

    } else {

        if ($accion === 'autorizar') {

            $nuevoEstado = 'activo';

        } else {

            $nuevoEstado = 'rechazado';
        }

        $sql = "
            UPDATE usuarios
            SET estadoUsuario = :nuevoEstado
            WHERE codUsuario = :codUsuario
              AND tipoUsuario = 'ceo aerolínea'
              AND estadoUsuario = 'pendiente de autorización'
        ";

        $consulta = $conexion->prepare($sql);

        $consulta->execute([
            'nuevoEstado' => $nuevoEstado,
            'codUsuario' => $codUsuario
        ]);

        if ($consulta->rowCount() > 0) {

            if ($accion === 'autorizar') {
                $mensaje = 'CEO autorizado correctamente.';
            } else {
                $mensaje = 'CEO rechazado correctamente.';
            }

        } else {

            $error = 'No se encontró un CEO pendiente con ese código.';
        }
    }
}

$porPagina = 5;

$paginaActual = (int) ($_GET['pagina'] ?? 1);

if ($paginaActual < 1) {
    $paginaActual = 1;
}

$sqlTotal = "
    SELECT COUNT(*) AS total
    FROM usuarios
    WHERE tipoUsuario = 'ceo aerolínea'
      AND estadoUsuario = 'pendiente de autorización'
";

$consultaTotal = $conexion->query($sqlTotal);

$resultadoTotal = $consultaTotal->fetch(PDO::FETCH_ASSOC);

$totalCeos = (int) $resultadoTotal['total'];


$totalPaginas = max(
    1,
    (int) ceil($totalCeos / $porPagina)
);


if ($paginaActual > $totalPaginas) {
    $paginaActual = $totalPaginas;
}


$offset = ($paginaActual - 1) * $porPagina;


$sql = "
    SELECT
        u.codUsuario,
        u.nombre,
        u.apellido,
        u.mail,
        u.telefono,
        a.nombre AS aerolinea

    FROM usuarios u

    LEFT JOIN aerolineas a
        ON u.codAerolinea = a.codAerolinea

    WHERE u.tipoUsuario = 'ceo aerolínea'
      AND u.estadoUsuario = 'pendiente de autorización'

    ORDER BY u.codUsuario DESC

    LIMIT :limite
    OFFSET :offset
";

$consulta = $conexion->prepare($sql);

$consulta->bindValue(
    ':limite',
    $porPagina,
    PDO::PARAM_INT
);

$consulta->bindValue(
    ':offset',
    $offset,
    PDO::PARAM_INT
);

$consulta->execute();

$ceosPendientes = $consulta->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <title>Autorización de CEOs - Volara</title>

</head>

<body>

    <h1>Autorización de CEOs</h1>

    <?php if ($mensaje !== ''): ?>

        <p>
            <?= htmlspecialchars($mensaje) ?>
        </p>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <p>
            <?= htmlspecialchars($error) ?>
        </p>

    <?php endif; ?>


    <?php if (empty($ceosPendientes)): ?>

        <p>No hay CEOs pendientes de autorización.</p>

    <?php else: ?>

        <table>

            <thead>

                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Teléfono</th>
                    <th>Aerolínea</th>
                    <th>Acciones</th>
                </tr>

            </thead>

            <tbody>

                <?php foreach ($ceosPendientes as $ceo): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars(
                                $ceo['nombre'] . ' ' . $ceo['apellido']
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($ceo['mail']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($ceo['telefono'] ?? '-') ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($ceo['aerolinea'] ?? '-') ?>
                        </td>

                        <td>

                            <form method="post">

                                <input
                                    type="hidden"
                                    name="codUsuario"
                                    value="<?= $ceo['codUsuario'] ?>"
                                >

                                <button
                                    type="submit"
                                    name="accion"
                                    value="autorizar"
                                >
                                    Autorizar
                                </button>

                                <button
                                    type="submit"
                                    name="accion"
                                    value="rechazar"
                                >
                                    Rechazar
                                </button>

                            </form>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

        <?php if ($totalPaginas > 1): ?>

            <nav>

                <?php for ($pagina = 1; $pagina <= $totalPaginas; $pagina++): ?>

                    <a href="?pagina=<?= $pagina ?>">
                        <?= $pagina ?>
                    </a>

                <?php endfor; ?>

            </nav>

        <?php endif; ?>

    <?php endif; ?>

</body>

</html>