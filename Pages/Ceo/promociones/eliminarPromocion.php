<?php
require_once __DIR__ . '/../../../Includes/functions.php';
require_once __DIR__ . '/../../../Includes/funcionPromociones.php';
requerirRol('ceo aerolínea');

$codPromocion = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$conexion = conectarDB();

if (empty($_SESSION['csrf_eliminar_promocion'])) {
    $_SESSION['csrf_eliminar_promocion'] =
        bin2hex(random_bytes(32));
}

$csrfToken =
    $_SESSION['csrf_eliminar_promocion'];

$consulta = $conexion->prepare(
    "SELECT codPromocion, descripcion, descuento, destino, fechaDesde, fechaHasta, estado
     FROM promociones
     WHERE codPromocion = :codPromocion
       AND codAerolinea = :codAerolinea"
);
$consulta->execute([
    'codPromocion' => $codPromocion,
    'codAerolinea' => $_SESSION['codAerolinea'],
]);
$promocion = $consulta->fetch(PDO::FETCH_ASSOC);

if (!$promocion) {
    $_SESSION['flash_promociones'] = [
        'tipo'  => 'danger',
        'texto' => 'La promoción que querés eliminar no existe.',
    ];
    header('Location: promociones.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrfRecibido =
        $_POST['csrf_token'] ?? '';

    if (
        !hash_equals(
            $csrfToken,
            (string) $csrfRecibido
        )
    ) {
        $_SESSION['flash_promociones'] = [
            'tipo'  => 'danger',
            'texto' => 'La solicitud no es válida. No se eliminó la promoción.',
        ];
        header('Location: promociones.php');
        exit;
    }

    try {
        $consulta = $conexion->prepare(
            "DELETE FROM promociones
             WHERE codPromocion = :codPromocion
               AND codAerolinea = :codAerolinea"
        );
        $consulta->execute([
            'codPromocion' => $codPromocion,
            'codAerolinea' => $_SESSION['codAerolinea'],
        ]);

        $_SESSION['flash_promociones'] = [
            'tipo'  => 'success',
            'texto' => 'La promoción se eliminó correctamente.',
        ];
    } catch (PDOException $e) {
        error_log('VOLARA - Error al eliminar promoción: ' . $e->getMessage());
        $_SESSION['flash_promociones'] = [
            'tipo'  => 'danger',
            'texto' => 'No se pudo eliminar la promoción. Intentá nuevamente.',
        ];
    }

    $_SESSION['csrf_eliminar_promocion'] =
        bin2hex(random_bytes(32));

    header('Location: promociones.php');
    exit;
}

$clasesEstado = [
    'pendiente' => 'badge-pending',
    'aprobada'  => 'badge-approved',
    'denegada'  => 'badge-denied',
];

$titulo = 'Eliminar promoción';
require_once __DIR__ . '/../../../Includes/header.php';
require_once __DIR__ . '/../../../Includes/navbar.php';
?>

<main id="contenido-principal" class="container my-4">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <h1>Eliminar promoción</h1>
            <p>¿Seguro que querés eliminar esta promoción? No se puede deshacer.</p>

            <?php if ($promocion['estado'] === 'aprobada'): ?>
                <div class="volara-alert alert-warning" role="status">
                    Esta promoción está aprobada. Si la eliminás, tu aerolínea se queda sin promoción
                    vigente. Las reservas que ya la usaron conservan el precio que pagaron.
                </div>
            <?php endif; ?>

            <div class="volara-card-flat mb-4">
                <p class="mb-2"><strong><?= htmlspecialchars($promocion['descripcion']) ?></strong></p>
                <p class="mb-1">
                    <?= htmlspecialchars($promocion['descuento']) ?>% de descuento
                    <span class="volara-badge <?= $clasesEstado[$promocion['estado']] ?? 'badge-neutral' ?> ms-2">
                        <?= htmlspecialchars($promocion['estado']) ?>
                    </span>
                </p>
                <p class="mb-1 text-muted">Destino: <?= htmlspecialchars($promocion['destino'] ?? 'Todos') ?></p>
                <p class="mb-0 text-muted">
                    Vigencia: <?= htmlspecialchars(textoVigencia($promocion['fechaDesde'], $promocion['fechaHasta'])) ?>
                </p>
            </div>

            <form method="post" class="d-flex gap-2 flex-wrap">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $csrfToken,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <button
                type="submit"
                class="btn-volara"
            >
                Sí, eliminar
            </button>
                <a href="promociones.php" class="btn-volara-outline">Cancelar</a>
            </form>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../../Includes/footer.php'; ?>