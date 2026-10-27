<?php
require_once __DIR__ . '/../../../Includes/functions.php';
require_once __DIR__ . '/../../../Includes/funcionPromociones.php';
requerirRol('administrador');

$conexion = conectarDB();

$error = '';
$hoy = date('Y-m-d');

if (empty($_SESSION['csrf_promociones_admin'])) {
    $_SESSION['csrf_promociones_admin'] =
        bin2hex(random_bytes(32));
}

$csrfToken =
    $_SESSION['csrf_promociones_admin'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codPromocion =
    (int) ($_POST['codPromocion'] ?? 0);

    $accion =
        $_POST['accion'] ?? '';

    $paginaVuelta =
        max(
            1,
            (int) ($_POST['pagina'] ?? 1)
        );

    $csrfRecibido =
        $_POST['csrf_token'] ?? '';


    if (
        !hash_equals(
            $csrfToken,
            (string) $csrfRecibido
        )
    ) {

        $error =
            'La solicitud no es válida. Recargá la página e intentá nuevamente.';

} elseif (
    $codPromocion < 1 ||
    !in_array(
        $accion,
        ['aprobar', 'denegar'],
        true
    )
) {

    $error =
        'La solicitud no es válida.';

}  else {
        $nuevoEstado = $accion === 'aprobar' ? 'aprobada' : 'denegada';
        try {
            $conexion->beginTransaction();

            if ($accion === 'aprobar') {
                $consulta = $conexion->prepare(
                    "SELECT codAerolinea, fechaHasta FROM promociones
                     WHERE codPromocion = :codPromocion AND estado = 'pendiente'"
                );
                $consulta->execute(['codPromocion' => $codPromocion]);
                $datos = $consulta->fetch(PDO::FETCH_ASSOC);

                if ($datos !== false) {
                    if ($datos['fechaHasta'] !== null && $datos['fechaHasta'] < $hoy) {
                        $conexion->rollBack();
                        $error = 'No se puede aprobar una promoción vencida. Podés denegarla.';
                    } else {
                        // La vigente actual (si hay) vuelve a pendiente
                        $consulta = $conexion->prepare(
                            "UPDATE promociones SET estado = 'pendiente'
                             WHERE codAerolinea = :codAerolinea AND estado = 'aprobada'"
                        );
                        $consulta->execute(['codAerolinea' => $datos['codAerolinea']]);
                    }
                }
            }

            if ($error === '') {
                $consulta = $conexion->prepare(
                    "UPDATE promociones SET estado = :estado
                     WHERE codPromocion = :codPromocion AND estado = 'pendiente'"
                );
                $consulta->execute([
                    'estado'       => $nuevoEstado,
                    'codPromocion' => $codPromocion,
                ]);

                if ($consulta->rowCount() === 0) {
                    $conexion->rollBack();
                    $error = 'Esa promoción ya no está pendiente.';
                } else {
                    $conexion->commit();

                    $_SESSION['csrf_promociones_admin'] =
                        bin2hex(random_bytes(32));

                    header(
                        'Location: promocionesPendientes.php?pagina='
                        . $paginaVuelta
                    );

                    exit;
                }
            }
        } catch (PDOException $e) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }
            if ($e->getCode() === '45000') {
                $error = 'No se pudo aprobar: la aerolínea ya tiene una promoción aprobada.';
            } else {
                throw $e;
            }
        }
    }
}

$totalPendientes = (int) $conexion
    ->query("SELECT COUNT(*) FROM promociones WHERE estado = 'pendiente'")
    ->fetchColumn();

$porPagina = 5;
$pagina = isset($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
$totalPaginas = max(1, (int) ceil($totalPendientes / $porPagina));
$pagina = max(1, min($pagina, $totalPaginas));
$offset = ($pagina - 1) * $porPagina;

$consulta = $conexion->prepare(
    "SELECT p.codPromocion, p.descripcion, p.descuento, p.destino,
            p.fechaDesde, p.fechaHasta, a.nombre AS aerolinea
     FROM promociones p
     INNER JOIN aerolineas a ON a.codAerolinea = p.codAerolinea
     WHERE p.estado = 'pendiente'
     ORDER BY p.codPromocion DESC
     LIMIT :limite OFFSET :offset"
);
$consulta->bindValue(':limite', $porPagina, PDO::PARAM_INT);
$consulta->bindValue(':offset', $offset, PDO::PARAM_INT);
$consulta->execute();
$pendientes = $consulta->fetchAll(PDO::FETCH_ASSOC);

$titulo = 'Promociones pendientes';
require_once __DIR__ . '/../../../Includes/header.php';
require_once __DIR__ . '/../../../Includes/navbar.php';
?>

<main id="contenido-principal" class="container my-4">
    <h1>Promociones pendientes</h1>
    <p class="text-muted">
        Al aprobar una promoción, la que esté vigente de esa aerolínea vuelve a quedar pendiente.
    </p>

    <?php if ($error !== ''): ?>
        <div class="volara-alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (empty($pendientes)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="bi bi-check2-circle" aria-hidden="true"></i></div>
            <p>No hay promociones pendientes de revisión.</p>
        </div>
    <?php else: ?>
        <div class="volara-table-responsive">
            <table class="volara-table">
                <thead>
                    <tr>
                        <th scope="col">Aerolínea</th>
                        <th scope="col">Descripción</th>
                        <th scope="col">Descuento</th>
                        <th scope="col">Destino</th>
                        <th scope="col">Vigencia</th>
                        <th scope="col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendientes as $promocion): ?>
                        <?php $vencida = $promocion['fechaHasta'] !== null && $promocion['fechaHasta'] < $hoy; ?>
                        <tr>
                            <td><?= htmlspecialchars($promocion['aerolinea']) ?></td>
                            <td><?= htmlspecialchars($promocion['descripcion']) ?></td>
                            <td><?= htmlspecialchars($promocion['descuento']) ?>%</td>
                            <td><?= htmlspecialchars($promocion['destino'] ?? 'Todos') ?></td>
                            <td>
                                <?= htmlspecialchars(textoVigencia($promocion['fechaDesde'], $promocion['fechaHasta'])) ?>
                                <?php if ($vencida): ?>
                                    <span class="volara-badge badge-cancelled ms-1">Vencida</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" class="table-actions">
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= htmlspecialchars(
                                            $csrfToken,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >
                                    <input type="hidden" name="codPromocion" value="<?= (int) $promocion['codPromocion'] ?>">
                                    <input type="hidden" name="pagina" value="<?= $pagina ?>">
                                    <?php if (!$vencida): ?>
                                        <button type="submit" name="accion" value="aprobar"
                                                class="table-action-btn" aria-label="Aprobar promoción" title="Aprobar">
                                            <i class="bi bi-check-lg" aria-hidden="true"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button type="submit" name="accion" value="denegar"
                                            class="table-action-btn danger" aria-label="Denegar promoción" title="Denegar">
                                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="volara-pagination-container">
            <nav class="volara-pagination" aria-label="Paginación de promociones pendientes">
                <ul class="pagination">
                    <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?pagina=<?= max(1, $pagina - 1) ?>"
                           <?= $pagina <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Anterior</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                        <li class="page-item <?= $i === $pagina ? 'active' : '' ?>"
                            <?= $i === $pagina ? 'aria-current="page"' : '' ?>>
                            <a class="page-link" href="?pagina=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>">
                        <a class="page-link" href="?pagina=<?= min($totalPaginas, $pagina + 1) ?>"
                           <?= $pagina >= $totalPaginas ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Siguiente</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../../../Includes/footer.php'; ?>