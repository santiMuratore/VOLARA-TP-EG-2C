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

<?php

$titulo = 'Autorización de CEOs';
$pageDescription =
    'Revisá y gestioná las solicitudes de CEOs de aerolíneas.';

require __DIR__ . '/../../Includes/header.php';
require __DIR__ . '/../../Includes/navbar.php';

?>

<main
    id="contenido-principal"
    class="volara-public-page"
    tabindex="-1"
>

    <!-- Encabezado -->
    <section class="page-header">

        <div class="container">

            <h1>
                Autorización de CEOs
            </h1>

            <p class="text-muted mb-3">
                Revisá las solicitudes pendientes de los
                responsables de aerolíneas.
            </p>

            <nav aria-label="Ruta de navegación">

                <ol class="breadcrumb mb-0">

                    <li class="breadcrumb-item">

                        <a
                            href="<?= BASE_URL ?>/Pages/Publico/index.php"
                        >
                            Inicio
                        </a>

                    </li>

                    <li
                        class="breadcrumb-item active"
                        aria-current="page"
                    >
                        Autorización de CEOs
                    </li>

                </ol>

            </nav>

        </div>

    </section>


    <!-- Contenido -->
    <section class="section section-gray">

        <div class="container">


            <!-- Mensaje de éxito -->
            <?php if ($mensaje !== ''): ?>

                <div
                    class="volara-alert alert-success"
                    role="status"
                >

                    <i
                        class="bi bi-check-circle-fill"
                        aria-hidden="true"
                    ></i>

                    <span>
                        <?= htmlspecialchars(
                            $mensaje,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>

                </div>

            <?php endif; ?>


            <!-- Mensaje de error -->
            <?php if ($error !== ''): ?>

                <div
                    class="volara-alert alert-danger"
                    role="alert"
                >

                    <i
                        class="bi bi-exclamation-circle-fill"
                        aria-hidden="true"
                    ></i>

                    <span>
                        <?= htmlspecialchars(
                            $error,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>

                </div>

            <?php endif; ?>


            <!-- Encabezado del listado -->
            <div
                class="d-flex justify-content-between
                       align-items-center flex-wrap gap-3 mb-4"
            >

                <div>

                    <h2 class="section-title mb-1">
                        Solicitudes pendientes
                    </h2>

                    <p class="text-muted mb-0">

                        <?= $totalCeos ?>

                        <?= $totalCeos === 1
                            ? 'CEO pendiente de autorización'
                            : 'CEOs pendientes de autorización'
                        ?>

                    </p>

                </div>

                <span class="volara-badge badge-neutral">

                    <i
                        class="bi bi-person-check me-1"
                        aria-hidden="true"
                    ></i>

                    Administración

                </span>

            </div>


            <?php if (empty($ceosPendientes)): ?>

                <!-- Estado vacío -->
                <div class="volara-card text-center py-5">

                    <div
                        class="stat-icon stat-icon-red mx-auto mb-3"
                    >

                        <i
                            class="bi bi-person-check"
                            aria-hidden="true"
                        ></i>

                    </div>

                    <h2 class="h4">
                        No hay solicitudes pendientes
                    </h2>

                    <p class="text-muted mb-0">
                        Actualmente no hay CEOs esperando
                        autorización.
                    </p>

                </div>


            <?php else: ?>

                <!-- Tabla -->
                <div class="volara-card">

                    <div class="table-responsive">

                        <table
                            class="table align-middle mb-0"
                        >

                            <thead>

                                <tr>

                                    <th scope="col">
                                        Nombre
                                    </th>

                                    <th scope="col">
                                        Email
                                    </th>

                                    <th scope="col">
                                        Teléfono
                                    </th>

                                    <th scope="col">
                                        Aerolínea
                                    </th>

                                    <th
                                        scope="col"
                                        class="text-end"
                                    >
                                        Acciones
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach (
                                    $ceosPendientes as $ceo
                                ): ?>

                                    <tr>

                                        <!-- Nombre -->
                                        <td>

                                            <div
                                                class="d-flex
                                                       align-items-center
                                                       gap-2"
                                            >

                                                <div
                                                    class="stat-icon
                                                           stat-icon-red
                                                           mb-0"
                                                >

                                                    <i
                                                        class="bi bi-person"
                                                        aria-hidden="true"
                                                    ></i>

                                                </div>

                                                <strong>

                                                    <?= htmlspecialchars(
                                                        $ceo['nombre']
                                                        . ' '
                                                        . $ceo['apellido'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>

                                                </strong>

                                            </div>

                                        </td>


                                        <!-- Email -->
                                        <td class="text-break">

                                            <?= htmlspecialchars(
                                                $ceo['mail'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </td>


                                        <!-- Teléfono -->
                                        <td>

                                            <?= htmlspecialchars(
                                                $ceo['telefono'] ?? '-',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </td>


                                        <!-- Aerolínea -->
                                        <td>

                                            <span
                                                class="volara-badge
                                                       badge-neutral"
                                            >

                                                <i
                                                    class="bi bi-airplane me-1"
                                                    aria-hidden="true"
                                                ></i>

                                                <?= htmlspecialchars(
                                                    $ceo['aerolinea'] ?? '-',
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                            </span>

                                        </td>


                                        <!-- Acciones -->
                                        <td>

                                            <form
                                                method="post"
                                                class="d-flex
                                                       justify-content-end
                                                       flex-wrap gap-2"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="codUsuario"
                                                    value="<?= (int)
                                                        $ceo['codUsuario']
                                                    ?>"
                                                >


                                                <button
                                                    type="submit"
                                                    name="accion"
                                                    value="autorizar"
                                                    class="btn
                                                           btn-volara
                                                           btn-volara-sm"
                                                >

                                                    <i
                                                        class="bi
                                                               bi-check-lg
                                                               me-1"
                                                        aria-hidden="true"
                                                    ></i>

                                                    Autorizar

                                                </button>


                                                <button
                                                    type="submit"
                                                    name="accion"
                                                    value="rechazar"
                                                    class="btn
                                                           btn-volara-outline
                                                           btn-volara-sm"
                                                >

                                                    <i
                                                        class="bi
                                                               bi-x-lg
                                                               me-1"
                                                        aria-hidden="true"
                                                    ></i>

                                                    Rechazar

                                                </button>

                                            </form>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                </div>


                <!-- Paginación -->
                <?php if ($totalPaginas > 1): ?>

                    <nav
                        class="volara-pagination mt-4"
                        aria-label="Páginas de CEOs pendientes"
                    >

                        <?php for (
                            $pagina = 1;
                            $pagina <= $totalPaginas;
                            $pagina++
                        ): ?>

                            <a
                                href="?pagina=<?= $pagina ?>"
                                class="<?= $pagina === $paginaActual
                                    ? 'active'
                                    : ''
                                ?>"
                                <?= $pagina === $paginaActual
                                    ? 'aria-current="page"'
                                    : ''
                                ?>
                            >
                                <?= $pagina ?>
                            </a>

                        <?php endfor; ?>

                    </nav>

                <?php endif; ?>

            <?php endif; ?>

        </div>

    </section>

</main>


<?php
require __DIR__ . '/../../Includes/footer.php';
?>