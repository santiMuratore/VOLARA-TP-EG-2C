<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Localizar los archivos Includes
|--------------------------------------------------------------------------
*/

$directoriosPosibles = [
    dirname(__DIR__) . '/Includes',
    dirname(__DIR__, 2) . '/Includes',
];

$buscarArchivo = static function (string $nombreArchivo) use ($directoriosPosibles): ?string {
    foreach ($directoriosPosibles as $directorio) {
        $ruta = $directorio . '/' . $nombreArchivo;
        if (is_file($ruta)) {
            return $ruta;
        }
    }

    return null;
};

/*
|--------------------------------------------------------------------------
| Cargar funciones compartidas
|--------------------------------------------------------------------------
*/

$archivoFunciones = $buscarArchivo('functions.php');
$archivoFuncionesF2 = $buscarArchivo('functionsF2.php');

if ($archivoFunciones === null || $archivoFuncionesF2 === null) {
    error_log('VOLARA: no se encontraron functions.php y/o functionsF2.php.');
    http_response_code(500);
    exit('Error de configuración. Verificá la ubicación de los archivos Includes.');
}

require_once $archivoFunciones;
require_once $archivoFuncionesF2;

$baseUrl = rtrim(BASE_URL, '/');

/*
|--------------------------------------------------------------------------
| Verificar sesión y rol
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['codUsuario'])) {
    header('Location: ' . $baseUrl . '/Auth/login.php');
    exit;
}

$rolUsuario = trim((string) (
    $_SESSION['tipoUsuario']
    ?? $_SESSION['rol']
    ?? ''
));

$rolUsuario = function_exists('mb_strtolower')
    ? mb_strtolower($rolUsuario, 'UTF-8')
    : strtolower($rolUsuario);

$rolUsuario = strtr($rolUsuario, [
    'á' => 'a',
    'é' => 'e',
    'í' => 'i',
    'ó' => 'o',
    'ú' => 'u',
    'ü' => 'u',
]);

if (!in_array($rolUsuario, ['usuario', 'pasajero', 'cliente'], true)) {
    header('Location: ' . $baseUrl . '/Pages/Publico/index.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Datos de la página
|--------------------------------------------------------------------------
*/

$titulo = 'Mis reservas';
$pageDescription = 'Consultá tus reservas de vuelos en VOLARA.';

$reservas = [];
$error = '';
$mensajeExito = '';

if (empty($_SESSION['csrf_reservas'])) {
    $_SESSION['csrf_reservas'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_reservas'];

/*
|--------------------------------------------------------------------------
| Mensajes de retorno
|--------------------------------------------------------------------------
*/

$resultado = $_GET['resultado'] ?? '';

if ($resultado === 'confirmada') {
    $mensajeExito = 'El pago fue confirmado correctamente. Tu reserva quedó confirmada.';
} elseif ($resultado === 'cancelada') {
    $mensajeExito = 'La reserva fue cancelada correctamente.';
}

if (($_GET['creada'] ?? '') === '1') {
    $mensajeExito = 'Tu reserva fue creada correctamente y quedó pendiente de pago.';
}

/*
|--------------------------------------------------------------------------
| Procesar acciones y cargar reservas
|--------------------------------------------------------------------------
*/

try {
    $conexion = conectarDB();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $accion = trim((string) ($_POST['accion'] ?? ''));
        $codReserva = filter_var(
            $_POST['codReserva'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $csrfRecibido = $_POST['csrf_token'] ?? '';

        if (
            !is_string($csrfRecibido)
            || !hash_equals($csrfToken, $csrfRecibido)
        ) {
            $error = 'El formulario venció. Recargá la página e intentá nuevamente.';
        } elseif ($codReserva === false || $codReserva === null) {
            $error = 'La reserva seleccionada no es válida.';
        } elseif (!in_array($accion, ['confirmar', 'cancelar'], true)) {
            $error = 'La acción solicitada no es válida.';
        } else {
            try {
                $conexion->beginTransaction();

                // Buscar únicamente la reserva del pasajero autenticado.
                $sqlReservaActual = "
                    SELECT
                        r.codReserva,
                        r.codVuelo,
                        r.estadoReserva,
                        v.fecha,
                        v.hora
                    FROM reservas r
                    INNER JOIN vuelos v
                        ON v.codVuelo = r.codVuelo
                    WHERE r.codReserva = :codReserva
                      AND r.codUsuario = :codUsuario
                    FOR UPDATE
                ";

                $consulta = $conexion->prepare($sqlReservaActual);
                $consulta->execute([
                    'codReserva' => (int) $codReserva,
                    'codUsuario' => (int) $_SESSION['codUsuario'],
                ]);

                $reservaActual = $consulta->fetch(PDO::FETCH_ASSOC);

                if (!$reservaActual) {
                    throw new DomainException('La reserva no existe o no pertenece a tu cuenta.');
                }

                $estadoActual = strtolower(trim((string) $reservaActual['estadoReserva']));

                if ($accion === 'confirmar') {
                    if ($estadoActual !== 'pendiente de pago') {
                        throw new DomainException('Esta reserva ya no está pendiente de pago.');
                    }

                    $sqlConfirmar = "
                        UPDATE reservas
                        SET estadoReserva = 'confirmada',
                            fechaPago = NOW()
                        WHERE codReserva = :codReserva
                          AND codUsuario = :codUsuario
                          AND LOWER(TRIM(estadoReserva)) = 'pendiente de pago'
                    ";

                    $consulta = $conexion->prepare($sqlConfirmar);
                    $consulta->execute([
                        'codReserva' => (int) $codReserva,
                        'codUsuario' => (int) $_SESSION['codUsuario'],
                    ]);

                    if ($consulta->rowCount() !== 1) {
                        throw new DomainException('No se pudo confirmar el pago de esta reserva.');
                    }

                    $conexion->commit();
                    $_SESSION['csrf_reservas'] = bin2hex(random_bytes(32));

                    header('Location: ' . $baseUrl . '/Pages/Pasajero/mis-reservas.php?resultado=confirmada');
                    exit;
                }

                if ($accion === 'cancelar') {
                    if (!in_array($estadoActual, ['pendiente de pago', 'confirmada'], true)) {
                        throw new DomainException('Esta reserva ya no puede cancelarse.');
                    }

                    $fechaHoraSalida = (string) $reservaActual['fecha'] . ' ' . (string) $reservaActual['hora'];
                    $salida = strtotime($fechaHoraSalida);

                    if ($salida === false) {
                        throw new DomainException('No se pudo determinar la fecha de salida del vuelo.');
                    }

                    $segundosRestantes = $salida - time();
                    $setentaYDosHoras = 72 * 60 * 60;

                    if ($segundosRestantes < $setentaYDosHoras) {
                        throw new DomainException('La reserva solo puede cancelarse hasta 72 horas antes de la salida del vuelo.');
                    }

                    $sqlCancelar = "
                        UPDATE reservas
                        SET estadoReserva = 'cancelada',
                            fechaCancelacion = NOW()
                        WHERE codReserva = :codReserva
                          AND codUsuario = :codUsuario
                          AND LOWER(TRIM(estadoReserva)) IN ('pendiente de pago', 'confirmada')
                    ";

                    $consulta = $conexion->prepare($sqlCancelar);
                    $consulta->execute([
                        'codReserva' => (int) $codReserva,
                        'codUsuario' => (int) $_SESSION['codUsuario'],
                    ]);

                    if ($consulta->rowCount() !== 1) {
                        throw new DomainException('No se pudo cancelar la reserva. Actualizá la página e intentá nuevamente.');
                    }

                    // Liberar la capacidad ocupada por esta reserva.
                    $sqlLiberarAsiento = "
                        UPDATE vuelos
                        SET asientosDisponibles = asientosDisponibles + 1
                        WHERE codVuelo = :codVuelo
                          AND asientosDisponibles < asientosTotal
                    ";

                    $consulta = $conexion->prepare($sqlLiberarAsiento);
                    $consulta->execute([
                        'codVuelo' => (int) $reservaActual['codVuelo'],
                    ]);

                    if ($consulta->rowCount() !== 1) {
                        throw new DomainException('No se pudo devolver la capacidad al vuelo. La cancelación no se guardó.');
                    }

                    $conexion->commit();
                    $_SESSION['csrf_reservas'] = bin2hex(random_bytes(32));

                    header('Location: ' . $baseUrl . '/Pages/Pasajero/mis-reservas.php?resultado=cancelada');
                    exit;
                }
            } catch (DomainException $e) {
                if ($conexion->inTransaction()) {
                    $conexion->rollBack();
                }
                $error = $e->getMessage();
            } catch (Throwable $e) {
                if ($conexion->inTransaction()) {
                    $conexion->rollBack();
                }

                error_log('Error gestionando reserva VOLARA: ' . $e->getMessage());
                $error = 'No se pudo procesar la acción. Intentá nuevamente.';
            }
        }
    }

    // La columna asiento se incluye para mostrar el lugar elegido.
    $sqlReservas = "
        SELECT
            r.codReserva,
            r.estadoReserva,
            r.precioFinal,
            r.asiento,
            v.codigoVuelo,
            v.origen,
            v.destino,
            v.fecha,
            v.hora,
            v.clase,
            a.nombre AS nombreAerolinea
        FROM reservas r
        LEFT JOIN vuelos v
            ON v.codVuelo = r.codVuelo
        LEFT JOIN aerolineas a
            ON a.codAerolinea = v.codAerolinea
        WHERE r.codUsuario = :codUsuario
        ORDER BY v.fecha DESC, v.hora DESC, r.codReserva DESC
    ";

    $consulta = $conexion->prepare($sqlReservas);
    $consulta->execute([
        'codUsuario' => (int) $_SESSION['codUsuario'],
    ]);

    $reservas = $consulta->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error cargando reservas VOLARA: ' . $e->getMessage());
    $error = 'No se pudieron cargar tus reservas. Intentá nuevamente.';
}

/*
|--------------------------------------------------------------------------
| Localizar plantillas del sitio
|--------------------------------------------------------------------------
*/

$directorioPlantillas = null;

foreach ($directoriosPosibles as $directorio) {
    if (
        is_file($directorio . '/header.php')
        && is_file($directorio . '/navbar.php')
        && is_file($directorio . '/footer.php')
    ) {
        $directorioPlantillas = $directorio;
        break;
    }
}

if ($directorioPlantillas === null) {
    error_log('VOLARA: no se encontraron header.php, navbar.php y footer.php.');
    http_response_code(500);
    exit('No se encontraron las plantillas compartidas de VOLARA.');
}

require $directorioPlantillas . '/header.php';
require $directorioPlantillas . '/navbar.php';
?>

<main
    id="contenido-principal"
    class="volara-public-page volara-pasajero-page"
    tabindex="-1"
>
    <section class="page-header">
        <div class="container">
            <h1>Mis reservas</h1>
            <p class="text-muted mb-3">
                Consultá los detalles de tus vuelos, el asiento asignado y el estado de tus reservas.
            </p>

            <nav aria-label="Ruta de navegación">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="<?= htmlspecialchars($baseUrl . '/Pages/Publico/index.php', ENT_QUOTES, 'UTF-8') ?>">
                            Inicio
                        </a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        Mis reservas
                    </li>
                </ol>
            </nav>
        </div>
    </section>

    <section class="volara-public-section">
        <div class="container">

            <?php if ($mensajeExito !== ''): ?>
                <div class="volara-alert alert-success" role="status">
                    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                    <span><?= htmlspecialchars($mensajeExito, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                <div>
                    <h2 class="h4 mb-1">Tus viajes</h2>
                    <p class="text-muted mb-0">
                        <?= count($reservas) ?>
                        <?= count($reservas) === 1 ? 'reserva encontrada' : 'reservas encontradas' ?>
                    </p>
                </div>

                <a
                    href="<?= htmlspecialchars($baseUrl . '/Pages/Publico/vuelos.php', ENT_QUOTES, 'UTF-8') ?>"
                    class="btn btn-volara"
                >
                    <i class="bi bi-search me-2" aria-hidden="true"></i>
                    Buscar vuelos
                </a>
            </div>

            <?php if ($error === '' && empty($reservas)): ?>
                <div class="volara-card text-center py-5">
                    <div class="stat-icon stat-icon-red mx-auto mb-3">
                        <i class="bi bi-ticket-perforated" aria-hidden="true"></i>
                    </div>
                    <h2 class="h4">Todavía no tenés reservas</h2>
                    <p class="text-muted mb-4">
                        Cuando reserves un vuelo, vas a poder consultar sus detalles desde esta sección.
                    </p>
                    <a
                        href="<?= htmlspecialchars($baseUrl . '/Pages/Publico/vuelos.php', ENT_QUOTES, 'UTF-8') ?>"
                        class="btn btn-volara"
                    >
                        Explorar vuelos
                    </a>
                </div>

            <?php elseif (!empty($reservas)): ?>
                <div class="row g-4">
                    <?php foreach ($reservas as $reserva): ?>
                        <?php
                        $estado = trim((string) ($reserva['estadoReserva'] ?? 'Sin estado'));
                        $estadoNormalizado = strtolower($estado);

                        $claseEstado = match ($estadoNormalizado) {
                            'confirmada', 'confirmado', 'activa' => 'success',
                            'cancelada', 'cancelado', 'rechazada', 'rechazado' => 'danger',
                            'pendiente', 'pendiente de pago', 'pendiente_pago' => 'warning',
                            default => 'secondary',
                        };

                        $fechaVuelo = 'Fecha no disponible';
                        if (!empty($reserva['fecha'])) {
                            $marcaTiempo = strtotime((string) $reserva['fecha']);
                            if ($marcaTiempo !== false) {
                                $fechaVuelo = date('d/m/Y', $marcaTiempo);
                            }
                        }

                        $horaVuelo = !empty($reserva['hora'])
                            ? substr((string) $reserva['hora'], 0, 5)
                            : 'Hora no disponible';

                        $precio = is_numeric($reserva['precioFinal'] ?? null)
                            ? (float) $reserva['precioFinal']
                            : null;

                        $asiento = trim((string) ($reserva['asiento'] ?? ''));

                        $puedeCancelar = false;
                        if (
                            in_array($estadoNormalizado, ['pendiente de pago', 'confirmada'], true)
                            && !empty($reserva['fecha'])
                            && !empty($reserva['hora'])
                        ) {
                            $salida = strtotime($reserva['fecha'] . ' ' . $reserva['hora']);
                            if ($salida !== false) {
                                $puedeCancelar = ($salida - time()) >= (72 * 60 * 60);
                            }
                        }
                        ?>

                        <div class="col-12 col-lg-6">
                            <article class="volara-card reservation-summary h-100">
                                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                    <div class="min-w-0">
                                        <p class="text-muted small mb-1">
                                            Reserva #<?= (int) $reserva['codReserva'] ?>
                                        </p>

                                        <h3 class="h5 mb-2">
                                            <?= htmlspecialchars((string) ($reserva['origen'] ?? 'Origen no disponible'), ENT_QUOTES, 'UTF-8') ?>
                                            <i class="bi bi-arrow-right text-danger mx-1" aria-hidden="true"></i>
                                            <?= htmlspecialchars((string) ($reserva['destino'] ?? 'Destino no disponible'), ENT_QUOTES, 'UTF-8') ?>
                                        </h3>

                                        <p class="text-muted small mb-0">
                                            <?= htmlspecialchars((string) ($reserva['nombreAerolinea'] ?? 'Aerolínea no disponible'), ENT_QUOTES, 'UTF-8') ?>
                                        </p>
                                    </div>

                                    <span class="badge text-bg-<?= htmlspecialchars($claseEstado, ENT_QUOTES, 'UTF-8') ?> flex-shrink-0">
                                        <?= htmlspecialchars($estado, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </div>

                                <hr>

                                <div class="reservation-summary-details">
                                    <p>
                                        <i class="bi bi-airplane me-2" aria-hidden="true"></i>
                                        <strong>Vuelo:</strong>
                                        <?= htmlspecialchars((string) ($reserva['codigoVuelo'] ?? 'No disponible'), ENT_QUOTES, 'UTF-8') ?>
                                    </p>

                                    <p>
                                        <i class="bi bi-calendar3 me-2" aria-hidden="true"></i>
                                        <strong>Fecha:</strong> <?= htmlspecialchars($fechaVuelo, ENT_QUOTES, 'UTF-8') ?>
                                    </p>

                                    <p>
                                        <i class="bi bi-clock me-2" aria-hidden="true"></i>
                                        <strong>Hora:</strong> <?= htmlspecialchars($horaVuelo, ENT_QUOTES, 'UTF-8') ?>
                                    </p>

                                    <p>
                                        <i class="bi bi-grid me-2" aria-hidden="true"></i>
                                        <strong>Clase:</strong>
                                        <?= htmlspecialchars((string) ($reserva['clase'] ?? 'No especificada'), ENT_QUOTES, 'UTF-8') ?>
                                    </p>

                                    <p>
                                        <i class="bi bi-person-workspace me-2" aria-hidden="true"></i>
                                        <strong>Asiento:</strong>
                                        <?php if ($asiento !== ''): ?>
                                            <span class="badge text-bg-light border">
                                                <?= htmlspecialchars($asiento, ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">No asignado</span>
                                        <?php endif; ?>
                                    </p>
                                </div>

                                <div class="reservation-summary-price">
                                    <span class="text-muted">Precio de la reserva</span>
                                    <strong>
                                        <?php if ($precio !== null): ?>
                                            $<?= number_format($precio, 2, ',', '.') ?>
                                        <?php else: ?>
                                            No disponible
                                        <?php endif; ?>
                                    </strong>
                                </div>

                                <div class="d-flex flex-wrap gap-2 mt-4">
                                    <?php if ($estadoNormalizado === 'pendiente de pago'): ?>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="codReserva" value="<?= (int) $reserva['codReserva'] ?>">
                                            <input type="hidden" name="accion" value="confirmar">
                                            <button type="submit" class="btn btn-volara">
                                                <i class="bi bi-credit-card me-1" aria-hidden="true"></i>
                                                Confirmar pago
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($puedeCancelar): ?>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="codReserva" value="<?= (int) $reserva['codReserva'] ?>">
                                            <input type="hidden" name="accion" value="cancelar">
                                            <button type="submit" class="btn btn-volara-outline">
                                                <i class="bi bi-x-circle me-1" aria-hidden="true"></i>
                                                Cancelar reserva
                                            </button>
                                        </form>
                                    <?php elseif (in_array($estadoNormalizado, ['pendiente de pago', 'confirmada'], true)): ?>
                                        <p class="text-muted small mb-0 align-self-center">
                                            <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                                            Ya no puede cancelarse porque faltan menos de 72 horas para la salida.
                                        </p>
                                    <?php endif; ?>

                                    <?php if ($estadoNormalizado === 'cancelada'): ?>
                                        <span class="text-muted small">
                                            <i class="bi bi-x-circle me-1" aria-hidden="true"></i>
                                            Esta reserva fue cancelada.
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </article>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require $directorioPlantillas . '/footer.php'; ?>
