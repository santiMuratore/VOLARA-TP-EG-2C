<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../Includes/functions.php';
require_once __DIR__ . '/../../Includes/functionsF2.php';

$baseUrl = rtrim(BASE_URL, '/');

/* --------------------------------------------------------------------------
 * Autenticación y autorización
 * -------------------------------------------------------------------------- */

if (empty($_SESSION['codUsuario'])) {
    header('Location: ' . $baseUrl . '/Auth/login.php');
    exit;
}

$rol = trim((string) ($_SESSION['tipoUsuario'] ?? $_SESSION['rol'] ?? ''));
$rol = function_exists('mb_strtolower')
    ? mb_strtolower($rol, 'UTF-8')
    : strtolower($rol);

$rol = strtr($rol, [
    'á' => 'a',
    'é' => 'e',
    'í' => 'i',
    'ó' => 'o',
    'ú' => 'u',
    'ü' => 'u',
]);

if (!in_array($rol, ['pasajero', 'usuario', 'cliente'], true)) {
    header('Location: ' . $baseUrl . '/Pages/Publico/index.php');
    exit;
}

/* --------------------------------------------------------------------------
 * Datos iniciales
 * -------------------------------------------------------------------------- */

$titulo = 'Elegir asientos';
$pageDescription = 'Elegí de uno a seis asientos y confirmá tu reserva en VOLARA.';

$codVueloRecibido = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['codVuelo'] ?? null)
    : ($_GET['codVuelo'] ?? null);

$codVueloValidado = filter_var(
    $codVueloRecibido,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$codVuelo = ($codVueloValidado === false || $codVueloValidado === null)
    ? 0
    : (int) $codVueloValidado;

$usuario = [];

if (function_exists('usuarioActual')) {
    $datosUsuario = usuarioActual();

    if (is_array($datosUsuario)) {
        $usuario = $datosUsuario;
    }
}

$nombrePasajero = trim(
    (string) ($usuario['nombre'] ?? $_SESSION['nombre'] ?? '') . ' ' .
    (string) ($usuario['apellido'] ?? $_SESSION['apellido'] ?? '')
);

if ($nombrePasajero === '') {
    $nombrePasajero = 'Pasajero';
}

if (empty($_SESSION['csrf_reserva'])) {
    $_SESSION['csrf_reserva'] = bin2hex(random_bytes(32));
}

$csrfToken = (string) $_SESSION['csrf_reserva'];

$conexion = null;
$vuelo = null;
$promocion = null;
$asientosMapa = [];
$asientosOcupados = [];
$error = null;

/*
 * Recibir los asientos seleccionados.
 * La validación definitiva de la cantidad se realiza al procesar el POST.
 */
$asientosRecibidos = $_POST['asientos'] ?? [];

if (!is_array($asientosRecibidos)) {
    $asientosRecibidos = [];
}

$asientosSeleccionados = [];

foreach ($asientosRecibidos as $asientoRecibido) {
    if (is_string($asientoRecibido)) {
        $etiqueta = strtoupper(trim($asientoRecibido));

        if ($etiqueta !== '') {
            $asientosSeleccionados[] = $etiqueta;
        }
    }
}

$asientosSeleccionados = array_values(array_unique($asientosSeleccionados));

$aplicarPromocion = $_SERVER['REQUEST_METHOD'] !== 'POST'
    || isset($_POST['aplicarPromocion']);

/* --------------------------------------------------------------------------
 * Generar etiquetas de asientos: 6 por fila (A, B, C - pasillo - D, E, F)
 * -------------------------------------------------------------------------- */

$generarAsientos = static function (int $cantidad): array {
    $columnas = ['A', 'B', 'C', 'D', 'E', 'F'];
    $asientos = [];

    for ($i = 0; $i < max(0, $cantidad); $i++) {
        $fila = intdiv($i, count($columnas)) + 1;
        $columna = $columnas[$i % count($columnas)];

        $asientos[] = [
            'fila' => $fila,
            'columna' => $columna,
            'etiqueta' => $fila . $columna,
        ];
    }

    return $asientos;
};

/* --------------------------------------------------------------------------
 * Obtener asientos ocupados a partir de reservas existentes
 * -------------------------------------------------------------------------- */

$obtenerAsientosOcupados = static function (
    PDO $db,
    int $idVuelo,
    array $asientosValidos
): array {
    $sql = "
        SELECT asiento
        FROM reservas
        WHERE codVuelo = :codVuelo
          AND LOWER(TRIM(estadoReserva)) NOT IN (
              'cancelada',
              'cancelado',
              'anulada',
              'anulado',
              'rechazada',
              'rechazado'
          )
    ";

    $consulta = $db->prepare($sql);
    $consulta->execute(['codVuelo' => $idVuelo]);

    $reservas = $consulta->fetchAll(PDO::FETCH_ASSOC);

    $etiquetasValidas = array_column($asientosValidos, 'etiqueta');
    $ocupados = [];
    $reservasSinAsiento = 0;

    foreach ($reservas as $reserva) {
        $etiqueta = strtoupper(trim((string) ($reserva['asiento'] ?? '')));

        if (
            $etiqueta !== ''
            && in_array($etiqueta, $etiquetasValidas, true)
        ) {
            $ocupados[$etiqueta] = true;
        } else {
            /*
             * Compatibilidad con reservas antiguas sin un asiento informado:
             * se les asigna visualmente un asiento libre.
             */
            $reservasSinAsiento++;
        }
    }

    foreach ($asientosValidos as $asiento) {
        if ($reservasSinAsiento <= 0) {
            break;
        }

        $etiqueta = $asiento['etiqueta'];

        if (!isset($ocupados[$etiqueta])) {
            $ocupados[$etiqueta] = true;
            $reservasSinAsiento--;
        }
    }

    return $ocupados;
};

/* --------------------------------------------------------------------------
 * Consultar vuelo y promoción
 * -------------------------------------------------------------------------- */

try {
    $conexion = conectarDB();

    if ($codVuelo <= 0) {
        $error = 'No se recibió un vuelo válido.';
    } else {
        $sql = "
            SELECT
                v.codVuelo,
                v.codigoVuelo,
                v.codAerolinea,
                v.origen,
                v.destino,
                v.fecha,
                v.hora,
                v.precio,
                v.asientosTotal,
                v.asientosDisponibles,
                v.clase,
                a.nombre AS nombreAerolinea
            FROM vuelos v
            INNER JOIN aerolineas a
                ON a.codAerolinea = v.codAerolinea
            WHERE v.codVuelo = :codVuelo
              AND v.activo = 1
            LIMIT 1
        ";

        $consulta = $conexion->prepare($sql);
        $consulta->execute(['codVuelo' => $codVuelo]);

        $vuelo = $consulta->fetch(PDO::FETCH_ASSOC);

        if (!$vuelo) {
            $error = 'El vuelo seleccionado no existe o ya no está disponible.';
        } elseif ((int) $vuelo['asientosTotal'] <= 0) {
            $error = 'Este vuelo no tiene una capacidad de asientos válida.';
        } else {
            $sqlPromocion = "
                SELECT codPromocion, descripcion, descuento
                FROM promociones
                WHERE codAerolinea = :codAerolinea
                  AND LOWER(estado) = 'aprobada'
                ORDER BY codPromocion DESC
                LIMIT 1
            ";

            $consultaPromocion = $conexion->prepare($sqlPromocion);
            $consultaPromocion->execute([
                'codAerolinea' => $vuelo['codAerolinea'],
            ]);

            $promocion = $consultaPromocion->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($promocion === null) {
                $aplicarPromocion = false;
            }
        }
    }
} catch (PDOException $e) {
    error_log(
        'VOLARA: error consultando vuelo/asientos: ' . $e->getMessage()
    );

    $error = 'No se pudo cargar el vuelo. Intentá nuevamente más tarde.';
}

/* --------------------------------------------------------------------------
 * Confirmar de 1 a 6 asientos en una única operación
 *
 * Cada asiento genera una fila en reservas para el mismo usuario y vuelo.
 * -------------------------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $vuelo && $error === null) {
    $tokenRecibido = $_POST['csrf_token'] ?? '';

    if (
        !is_string($tokenRecibido)
        || !hash_equals($csrfToken, $tokenRecibido)
    ) {
        $error = 'El formulario venció. Recargá la página e intentá nuevamente.';
    } elseif (count($asientosSeleccionados) < 1) {
        $error = 'Seleccioná al menos un asiento antes de continuar.';
    } elseif (count($asientosSeleccionados) > 6) {
        $error = 'Podés reservar como máximo 6 asientos por operación.';
    } else {
        try {
            $conexion->beginTransaction();

            /*
             * Bloquear el vuelo para evitar que dos operaciones descuenten
             * simultáneamente la misma disponibilidad.
             */
            $sqlVueloBloqueado = "
                SELECT
                    codVuelo,
                    codAerolinea,
                    precio,
                    asientosTotal,
                    asientosDisponibles,
                    activo
                FROM vuelos
                WHERE codVuelo = :codVuelo
                FOR UPDATE
            ";

            $consulta = $conexion->prepare($sqlVueloBloqueado);
            $consulta->execute(['codVuelo' => $codVuelo]);

            $vueloActual = $consulta->fetch(PDO::FETCH_ASSOC);

            if (!$vueloActual || (int) $vueloActual['activo'] !== 1) {
                throw new DomainException(
                    'El vuelo ya no está disponible para reservar.'
                );
            }

            $cantidadSolicitada = count($asientosSeleccionados);
            $disponiblesActuales = (int) $vueloActual['asientosDisponibles'];

            if ($disponiblesActuales <= 0) {
                throw new DomainException(
                    'No quedan asientos disponibles para este vuelo.'
                );
            }

            if ($cantidadSolicitada > $disponiblesActuales) {
                throw new DomainException(
                    'Solo quedan ' . $disponiblesActuales .
                    ' asiento(s) disponible(s). Reducí la cantidad seleccionada.'
                );
            }

            $asientosValidos = $generarAsientos(
                (int) $vueloActual['asientosTotal']
            );

            $etiquetasValidas = array_column(
                $asientosValidos,
                'etiqueta'
            );

            foreach ($asientosSeleccionados as $etiqueta) {
                if (!in_array($etiqueta, $etiquetasValidas, true)) {
                    throw new DomainException(
                        'El asiento ' . $etiqueta .
                        ' no es válido para este vuelo.'
                    );
                }
            }

            $ocupadosActuales = $obtenerAsientosOcupados(
                $conexion,
                $codVuelo,
                $asientosValidos
            );

            foreach ($asientosSeleccionados as $etiqueta) {
                if (isset($ocupadosActuales[$etiqueta])) {
                    throw new DomainException(
                        'El asiento ' . $etiqueta .
                        ' ya fue reservado. Elegí otros asientos.'
                    );
                }
            }

            $precioFinal = (float) $vueloActual['precio'];
            $codPromocion = null;

            if (isset($_POST['aplicarPromocion'])) {
                $sqlPromocionActual = "
                    SELECT codPromocion, descuento
                    FROM promociones
                    WHERE codAerolinea = :codAerolinea
                      AND LOWER(estado) = 'aprobada'
                    ORDER BY codPromocion DESC
                    LIMIT 1
                ";

                $consultaPromocionActual = $conexion->prepare(
                    $sqlPromocionActual
                );

                $consultaPromocionActual->execute([
                    'codAerolinea' => $vueloActual['codAerolinea'],
                ]);

                $promocionActual = $consultaPromocionActual->fetch(
                    PDO::FETCH_ASSOC
                );

                if (!$promocionActual) {
                    throw new DomainException(
                        'La promoción ya no está disponible. Recargá la página.'
                    );
                }

                $descuento = (float) $promocionActual['descuento'];

                if ($descuento < 0 || $descuento > 100) {
                    throw new DomainException(
                        'El descuento configurado no es válido.'
                    );
                }

                $codPromocion = (int) $promocionActual['codPromocion'];

                $precioFinal = round(
                    $precioFinal * (1 - $descuento / 100),
                    2
                );
            }

            /*
             * Descontar los asientos juntos, verificando nuevamente
             * que la capacidad sea suficiente.
             */
            $sqlActualizar = "
                UPDATE vuelos
                SET asientosDisponibles = asientosDisponibles - :cantidad
                WHERE codVuelo = :codVuelo
                  AND activo = 1
                  AND asientosDisponibles >= :cantidadVerificacion
            ";

            $consultaActualizar = $conexion->prepare($sqlActualizar);

            $consultaActualizar->execute([
                'cantidad' => $cantidadSolicitada,
                'codVuelo' => $codVuelo,
                'cantidadVerificacion' => $cantidadSolicitada,
            ]);

            if ($consultaActualizar->rowCount() !== 1) {
                throw new DomainException(
                    'La disponibilidad cambió mientras reservabas. ' .
                    'Volvé a elegir los asientos.'
                );
            }

            $sqlInsertar = "
                INSERT INTO reservas (
                    codUsuario,
                    codVuelo,
                    codPromocion,
                    estadoReserva,
                    precioFinal,
                    asiento,
                    codCompra
                ) VALUES (
                    :codUsuario,
                    :codVuelo,
                    :codPromocion,
                    'pendiente de pago',
                    :precioFinal,
                    :asiento,
                    :codCompra
                )
            ";

            $consultaInsertar = $conexion->prepare($sqlInsertar);

            /*
             * Todos los asientos de esta compra comparten el mismo codCompra:
             * se pagan juntos con una única confirmación. Una compra posterior
             * del mismo vuelo genera otro codCompra y se paga por separado.
             */
            $codCompra = bin2hex(random_bytes(8));

            foreach ($asientosSeleccionados as $etiqueta) {
                $consultaInsertar->execute([
                    'codUsuario' => (int) $_SESSION['codUsuario'],
                    'codVuelo' => $codVuelo,
                    'codPromocion' => $codPromocion,
                    'precioFinal' => $precioFinal,
                    'asiento' => $etiqueta,
                    'codCompra' => $codCompra,
                ]);
            }

            $conexion->commit();

            $_SESSION['csrf_reserva'] = bin2hex(random_bytes(32));

            header(
                'Location: ' . $baseUrl .
                '/Pages/Pasajero/mis-reservas.php?creada=1&cantidad=' .
                $cantidadSolicitada
            );
            exit;
        } catch (DomainException $e) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }

            $error = $e->getMessage();
        } catch (Throwable $e) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }

            error_log(
                'VOLARA: error confirmando reserva múltiple: ' .
                $e->getMessage()
            );

            $error = 'No se pudo confirmar la reserva. Intentá nuevamente.';
        }
    }
}

/* --------------------------------------------------------------------------
 * Preparar mapa de asientos para mostrarlo
 * -------------------------------------------------------------------------- */

if ($vuelo && $conexion instanceof PDO) {
    try {
        // Actualizar la disponibilidad mostrada.
        $consultaDisponibilidad = $conexion->prepare(
            'SELECT asientosDisponibles
             FROM vuelos
             WHERE codVuelo = :codVuelo
               AND activo = 1'
        );

        $consultaDisponibilidad->execute(['codVuelo' => $codVuelo]);

        $disponibilidadActual = $consultaDisponibilidad->fetchColumn();

        if ($disponibilidadActual !== false) {
            $vuelo['asientosDisponibles'] = (int) $disponibilidadActual;
        }

        $asientosMapa = $generarAsientos(
            (int) $vuelo['asientosTotal']
        );

        $asientosOcupados = $obtenerAsientosOcupados(
            $conexion,
            $codVuelo,
            $asientosMapa
        );

        // Mantener marcados los elegidos que sigan libres.
        $asientosSeleccionados = array_values(array_filter(
            $asientosSeleccionados,
            static function ($etiqueta) use ($asientosOcupados) {
                return !isset($asientosOcupados[$etiqueta]);
            }
        ));
    } catch (PDOException $e) {
        error_log(
            'VOLARA: error cargando mapa de asientos: ' . $e->getMessage()
        );

        $error = 'No se pudo cargar el mapa de asientos.';
        $asientosMapa = [];
        $asientosOcupados = [];
    }
}

/* --------------------------------------------------------------------------
 * Formatear los datos que se muestran
 * -------------------------------------------------------------------------- */

$fechaVuelo = '';

if ($vuelo && !empty($vuelo['fecha'])) {
    $timestamp = strtotime((string) $vuelo['fecha']);

    if ($timestamp !== false) {
        $fechaVuelo = date('d/m/Y', $timestamp);
    }
}

$horaVuelo = $vuelo && !empty($vuelo['hora'])
    ? substr((string) $vuelo['hora'], 0, 5)
    : '';

$precioBase = $vuelo ? (float) $vuelo['precio'] : 0.0;
$precioPromocional = $precioBase;
$descuentoPromocion = 0.0;

if ($promocion) {
    $descuentoPromocion = (float) $promocion['descuento'];

    $precioPromocional = round(
        $precioBase * (1 - $descuentoPromocion / 100),
        2
    );
}

$cantidadSeleccionada = count($asientosSeleccionados);

$precioUnitarioMostrado = $aplicarPromocion
    ? $precioPromocional
    : $precioBase;

$totalEstimado = $cantidadSeleccionada * $precioUnitarioMostrado;

$titulo = 'Elegir asientos';

require_once __DIR__ . '/../../Includes/header.php';
require_once __DIR__ . '/../../Includes/navbar.php';
?>

<style>
.seat-selection-page {
    color: #151D25;
}

.seat-selection-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.4fr) minmax(280px, .8fr);
    gap: 1.5rem;
    align-items: start;
}

.seat-cabin-panel,
.seat-trip-summary .volara-card {
    min-width: 0;
    padding: 1.35rem;
    border: 1px solid #DDE2E7;
    border-radius: 16px;
    background: #FFFFFF;
    box-shadow: 0 4px 18px rgba(21, 29, 37, .04);
}

.seat-cabin-header {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: .8rem;
    margin-bottom: 1.25rem;
}

.seat-cabin-header h2 {
    margin: .3rem 0 0;
    font-size: 1.2rem;
    font-weight: 800;
}

.seat-class-label {
    padding: .4rem .7rem;
    border-radius: 999px;
    background: #FDEAEB;
    color: #C9141C;
    font-size: .8rem;
    font-weight: 750;
}

.seat-limit-note {
    margin: 0 0 1rem;
    padding: .75rem .9rem;
    border-radius: 10px;
    background: #F4F6F8;
    color: #4B5563;
    font-size: .9rem;
}

.seat-airplane {
    max-width: 460px;
    margin: 0 auto;
    padding: 1rem .75rem;
    overflow-x: auto;
    border: 1px solid #E6E9ED;
    border-radius: 18px;
    background: #F8FAFC;
}

.seat-cockpit {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .35rem;
    max-width: 240px;
    margin: 0 auto 1.4rem;
    padding: .9rem;
    border: 1px solid #DDE2E7;
    border-radius: 50% 50% 16px 16px;
    background: #FFFFFF;
    color: #6B7280;
    font-size: .78rem;
}

.seat-cockpit i {
    color: #E51B23;
    font-size: 1.5rem;
}

#seatMap {
    width: max-content;
    margin: 0 auto;
}

.seat-map-header,
.seat-row {
    display: grid;
    grid-template-columns: 25px repeat(3, 38px) 18px repeat(3, 38px);
    gap: 5px;
    align-items: center;
    justify-items: center;
}

.seat-map-header {
    margin-bottom: .65rem;
    color: #6B7280;
    font-size: .75rem;
    font-weight: 750;
}

.seat-map-header .cabin-label {
    grid-column: 1;
    font-size: .65rem;
}

.seat-column-labels {
    grid-column: 2 / -1;
    display: grid;
    grid-template-columns: repeat(3, 38px) 18px repeat(3, 38px);
    gap: 5px;
    justify-items: center;
    width: 100%;
}

.seat-row {
    margin-bottom: .5rem;
}

.seat-row-number {
    color: #6B7280;
    font-size: .75rem;
    font-weight: 700;
}

.seat-input {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

.seat {
    position: relative;
    display: inline-flex;
    box-sizing: border-box;
    width: 38px;
    min-width: 38px;
    height: 35px;
    align-items: center;
    justify-content: center;
    padding: .25rem 0 0;
    border: 1px solid #AEB9C6;
    border-bottom: 4px solid #8493A3;
    border-radius: 9px 9px 5px 5px;
    background: #FFFFFF;
    color: #374151;
    font-size: .7rem;
    font-weight: 750;
    cursor: pointer;
    user-select: none;
    transition:
        background-color .15s ease,
        border-color .15s ease,
        transform .15s ease;
}

.seat::before {
    position: absolute;
    top: 3px;
    right: 5px;
    left: 5px;
    height: 4px;
    border-radius: 4px;
    background: #C7D0DA;
    content: "";
}

.seat-input:checked + .seat {
    border-color: #C9141C;
    border-bottom-color: #9F1017;
    background: #E51B23;
    color: #FFFFFF;
}

.seat-input:checked + .seat::before {
    background: #FFFFFF;
}

.seat-input:not(:disabled) + .seat:hover {
    transform: translateY(-1px);
    border-color: #E51B23;
    border-bottom-color: #C9141C;
    background: #FDEAEB;
    color: #C9141C;
}

.seat-input:checked + .seat:hover {
    background: #E51B23;
    color: #FFFFFF;
}

.seat-input:focus-visible + .seat {
    outline: 3px solid rgba(229, 27, 35, .35);
    outline-offset: 3px;
}

.seat.occupied,
.seat.limit-reached {
    border-color: #D1D5DB;
    border-bottom-color: #B7C0CA;
    background: #E5E7EB;
    color: #9CA3AF;
    cursor: not-allowed;
    transform: none;
}

.seat.occupied::before,
.seat.limit-reached::before {
    background: #C5CBD3;
}

.seat-aisle {
    width: 18px;
}

.seat-legend {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: .8rem 1.1rem;
    margin-top: 1.25rem;
}

.seat-legend-item {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    color: #4B5563;
    font-size: .8rem;
}

.seat-legend-item .seat {
    width: 25px;
    min-width: 25px;
    height: 23px;
    padding: 0;
    border-radius: 6px 6px 4px 4px;
    cursor: default;
}

.seat-legend-item .seat::before {
    top: 2px;
    right: 4px;
    left: 4px;
    height: 3px;
}

.seat-legend-item .seat.selected {
    border-color: #C9141C;
    border-bottom-color: #9F1017;
    background: #E51B23;
    color: #FFFFFF;
}

.seat-summary-details {
    display: grid;
    margin: 1.2rem 0;
}

.seat-summary-details > div {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    padding: .8rem 0;
    border-bottom: 1px solid #E9ECEF;
}

.seat-summary-details dt {
    color: #6B7280;
    font-weight: 500;
}

.seat-summary-details dd {
    margin: 0;
    text-align: right;
    font-weight: 750;
    overflow-wrap: anywhere;
}

.seat-promo-box {
    margin: 1rem 0;
    padding: 1rem;
    border: 1px solid #CDE8D5;
    border-radius: 12px;
    background: #F4FBF6;
}

.seat-total-box {
    margin: 1rem 0;
    padding: 1rem;
    border-radius: 12px;
    background: #F4F6F8;
}

.seat-total-box .total-value {
    color: #C9141C;
    font-size: 1.35rem;
    font-weight: 850;
}

@media (max-width: 991.98px) {
    .seat-selection-layout {
        grid-template-columns: minmax(0, 1fr);
    }

    .seat-airplane {
        max-width: 420px;
    }
}

@media (max-width: 420px) {
    .seat-cabin-panel,
    .seat-trip-summary .volara-card {
        padding: .8rem;
    }

    .seat-airplane {
        padding: .8rem .4rem;
    }

    .seat-map-header,
    .seat-row {
        grid-template-columns: 20px repeat(3, 31px) 10px repeat(3, 31px);
        gap: 3px;
    }

    .seat-column-labels {
        grid-template-columns: repeat(3, 31px) 10px repeat(3, 31px);
        gap: 3px;
    }

    .seat {
        width: 31px;
        min-width: 31px;
        height: 31px;
        font-size: .62rem;
    }

    .seat-aisle {
        width: 10px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .seat {
        transition: none;
    }
}
</style>

<main id="contenido-principal" class="seat-selection-page" tabindex="-1">
    <section class="page-header">
        <div class="container">
            <p class="eyebrow mb-2">VOLARA · Reservas</p>
            <h1>Elegí tus asientos</h1>
            <p class="text-muted mb-0">
                Podés reservar de 1 a 6 asientos en una sola operación.
            </p>
        </div>
    </section>

    <section class="section">
        <div class="container">

            <?php if ($error !== null): ?>
                <div class="volara-alert alert-danger mb-4" role="alert">
                    <i class="bi bi-exclamation-circle me-2" aria-hidden="true"></i>
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if (!$vuelo): ?>

                <div class="volara-card text-center">
                    <i class="bi bi-airplane display-5 text-danger" aria-hidden="true"></i>
                    <h2 class="h4 mt-3">No se puede mostrar este vuelo</h2>
                    <p class="text-muted">
                        Volvé al listado y seleccioná un vuelo disponible.
                    </p>
                    <a
                        href="<?= htmlspecialchars($baseUrl . '/Pages/Publico/vuelos.php', ENT_QUOTES, 'UTF-8') ?>"
                        class="btn btn-volara"
                    >
                        Volver a buscar vuelos
                    </a>
                </div>

            <?php elseif (!$asientosMapa): ?>

                <div class="volara-card text-center">
                    <h2 class="h4">Mapa de asientos no disponible</h2>
                    <p class="text-muted mb-0">
                        No se pudo generar el mapa. Revisá la capacidad del vuelo
                        y la conexión con la base de datos.
                    </p>
                </div>

            <?php else: ?>

                <form method="POST" action="">
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
                    >

                    <input
                        type="hidden"
                        name="codVuelo"
                        value="<?= (int) $vuelo['codVuelo'] ?>"
                    >

                    <div class="seat-selection-layout">

                        <section
                            class="seat-cabin-panel"
                            aria-labelledby="cabin-title"
                        >
                            <div class="seat-cabin-header">
                                <div>
                                    <span class="eyebrow">
                                        <?= htmlspecialchars($vuelo['nombreAerolinea'], ENT_QUOTES, 'UTF-8') ?>
                                        ·
                                        <?= htmlspecialchars($vuelo['codigoVuelo'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>

                                    <h2 id="cabin-title">Seleccioná tus asientos</h2>
                                </div>

                                <span class="seat-class-label">
                                    <?= htmlspecialchars(ucfirst((string) $vuelo['clase']), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>

                            <p class="seat-limit-note" id="seatLimitMessage" aria-live="polite">
                                <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                                Marcá entre 1 y 6 asientos. Al seleccionar 6,
                                los demás asientos disponibles se deshabilitarán.
                            </p>

                            <div
                                class="seat-airplane"
                                aria-label="Mapa de asientos del avión"
                            >
                                <div class="seat-cockpit">
                                    <i class="bi bi-airplane" aria-hidden="true"></i>
                                    <span>Frente del avión</span>
                                </div>

                                <div id="seatMap">
                                    <div class="seat-map-header">
                                        <span class="cabin-label">Fila</span>

                                        <div class="seat-column-labels">
                                            <span>A</span>
                                            <span>B</span>
                                            <span>C</span>
                                            <span aria-hidden="true"></span>
                                            <span>D</span>
                                            <span>E</span>
                                            <span>F</span>
                                        </div>
                                    </div>

                                    <?php foreach (array_chunk($asientosMapa, 6) as $filaAsientos): ?>
                                        <div class="seat-row">
                                            <span class="seat-row-number">
                                                <?= (int) $filaAsientos[0]['fila'] ?>
                                            </span>

                                            <?php foreach ($filaAsientos as $asiento): ?>
                                                <?php
                                                $etiqueta = $asiento['etiqueta'];

                                                $ocupado = isset($asientosOcupados[$etiqueta])
                                                    || (int) $vuelo['asientosDisponibles'] <= 0;

                                                $seleccionado = !$ocupado
                                                    && in_array(
                                                        $etiqueta,
                                                        $asientosSeleccionados,
                                                        true
                                                    );

                                                $idAsiento = 'asiento-' . $etiqueta;
                                                ?>

                                                <?php if ($asiento['columna'] === 'D'): ?>
                                                    <span class="seat-aisle" aria-hidden="true"></span>
                                                <?php endif; ?>

                                                <input
                                                    type="checkbox"
                                                    class="seat-input"
                                                    name="asientos[]"
                                                    id="<?= htmlspecialchars($idAsiento, ENT_QUOTES, 'UTF-8') ?>"
                                                    value="<?= htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8') ?>"
                                                    aria-label="Asiento <?= htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8') ?>"
                                                    data-occupied="<?= $ocupado ? '1' : '0' ?>"
                                                    <?= $ocupado ? 'disabled' : '' ?>
                                                    <?= $seleccionado ? 'checked' : '' ?>
                                                >

                                                <label
                                                    for="<?= htmlspecialchars($idAsiento, ENT_QUOTES, 'UTF-8') ?>"
                                                    class="seat<?= $ocupado ? ' occupied' : '' ?>"
                                                    title="<?= htmlspecialchars(
                                                        'Asiento ' . $etiqueta .
                                                        ($ocupado ? ' — ocupado' : ' — disponible'),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>"
                                                >
                                                    <?= htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8') ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="seat-legend" aria-label="Leyenda de asientos">
                                <span class="seat-legend-item">
                                    <span class="seat" aria-hidden="true"></span>
                                    Disponible
                                </span>

                                <span class="seat-legend-item">
                                    <span class="seat selected" aria-hidden="true"></span>
                                    Seleccionado
                                </span>

                                <span class="seat-legend-item">
                                    <span class="seat occupied" aria-hidden="true"></span>
                                    Ocupado
                                </span>
                            </div>
                        </section>

                        <aside class="seat-trip-summary">
                            <div class="volara-card">
                                <span class="eyebrow">Resumen del viaje</span>

                                <h2 class="h4 mt-2">
                                    <?= htmlspecialchars($vuelo['origen'], ENT_QUOTES, 'UTF-8') ?>
                                    <span class="text-muted">→</span>
                                    <?= htmlspecialchars($vuelo['destino'], ENT_QUOTES, 'UTF-8') ?>
                                </h2>

                                <p class="text-muted mb-3">
                                    <?= htmlspecialchars($fechaVuelo, ENT_QUOTES, 'UTF-8') ?>
                                    ·
                                    <?= htmlspecialchars($horaVuelo, ENT_QUOTES, 'UTF-8') ?>
                                </p>

                                <dl class="seat-summary-details">
                                    <div>
                                        <dt>Reservado por</dt>
                                        <dd><?= htmlspecialchars($nombrePasajero, ENT_QUOTES, 'UTF-8') ?></dd>
                                    </div>

                                    <div>
                                        <dt>Asientos elegidos</dt>
                                        <dd><?= $cantidadSeleccionada ?> de 6</dd>
                                    </div>

                                    <div>
                                        <dt>Disponibles</dt>
                                        <dd><?= (int) $vuelo['asientosDisponibles'] ?></dd>
                                    </div>

                                    <div>
                                        <dt>Precio por persona</dt>
                                        <dd>$<?= number_format($precioUnitarioMostrado, 2, ',', '.') ?></dd>
                                    </div>
                                </dl>

                                <?php if ($promocion): ?>
                                    <div class="seat-promo-box">
                                        <div class="fw-bold mb-2">
                                            <i class="bi bi-tag me-1" aria-hidden="true"></i>
                                            Promoción disponible
                                        </div>

                                        <p class="small mb-2">
                                            <?= htmlspecialchars($promocion['descripcion'], ENT_QUOTES, 'UTF-8') ?>
                                        </p>

                                        <p class="small mb-2">
                                            Descuento:
                                            <?= number_format($descuentoPromocion, 0, ',', '.') ?>%
                                        </p>

                                        <div class="fw-bold mb-3">
                                            Precio por persona con promoción:
                                            $<?= number_format($precioPromocional, 2, ',', '.') ?>
                                        </div>

                                        <div class="form-check">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                id="aplicarPromocion"
                                                name="aplicarPromocion"
                                                value="1"
                                                <?= $aplicarPromocion ? 'checked' : '' ?>
                                            >

                                            <label
                                                class="form-check-label"
                                                for="aplicarPromocion"
                                            >
                                                Aplicar esta promoción a cada asiento
                                            </label>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="seat-total-box">
                                    <?php if ($cantidadSeleccionada > 0): ?>
                                        <div class="text-muted small mb-1">
                                            Total estimado para
                                            <?= $cantidadSeleccionada ?> asiento(s)
                                        </div>

                                        <div class="total-value">
                                            $<?= number_format($totalEstimado, 2, ',', '.') ?>
                                        </div>

                                        <p class="small text-muted mb-0 mt-2">
                                            Importe estimado. El precio definitivo se
                                            valida en el servidor al confirmar.
                                        </p>
                                    <?php else: ?>
                                        <div class="text-muted small mb-1">
                                            Precio por persona
                                        </div>

                                        <div class="total-value">
                                            $<?= number_format($precioUnitarioMostrado, 2, ',', '.') ?>
                                        </div>

                                        <p class="small text-muted mb-0 mt-2">
                                            Seleccioná entre 1 y 6 asientos y presioná
                                            confirmar. 
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <button type="submit" class="btn btn-volara w-100">
                                    Confirmar <?= $cantidadSeleccionada > 1 ? 'reservas' : 'reserva' ?>
                                    <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
                                </button>

                                <a
                                    href="<?= htmlspecialchars($baseUrl . '/Pages/Publico/vuelos.php', ENT_QUOTES, 'UTF-8') ?>"
                                    class="btn btn-volara-outline w-100 mt-2"
                                >
                                    Volver a vuelos
                                </a>
                            </div>
                        </aside>

                    </div>
                </form>

            <?php endif; ?>
        </div>
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const asientos = Array.from(
        document.querySelectorAll('.seat-input')
    );

    const limite = 6;
    const mensaje = document.getElementById('seatLimitMessage');

    function actualizarAsientos() {
        const seleccionados = asientos.filter(function (asiento) {
            return asiento.checked;
        }).length;

        asientos.forEach(function (asiento) {
            const etiqueta = document.querySelector(
                'label[for="' + asiento.id + '"]'
            );

            // Los asientos ya ocupados siempre permanecen deshabilitados.
            if (asiento.dataset.occupied === '1') {
                asiento.disabled = true;

                if (etiqueta) {
                    etiqueta.classList.remove('limit-reached');
                }

                return;
            }

            /*
             * Al alcanzar el máximo, se deshabilitan solo los asientos
             * disponibles que no estén seleccionados.
             */
            const bloquearPorLimite =
                seleccionados >= limite && !asiento.checked;

            asiento.disabled = bloquearPorLimite;

            if (etiqueta) {
                etiqueta.classList.toggle(
                    'limit-reached',
                    bloquearPorLimite
                );

                etiqueta.title = bloquearPorLimite
                    ? 'Asiento no seleccionable: ya elegiste 6 asientos'
                    : 'Asiento ' + asiento.value + ' — disponible';
            }
        });

        if (mensaje) {
            const icono = '<i class="bi bi-info-circle me-1" aria-hidden="true"></i>';

            if (seleccionados >= limite) {
                mensaje.innerHTML = icono +
                    'Seleccionaste 6 de 6 asientos. ' +
                    'Desmarcá uno para elegir otro.';
            } else {
                mensaje.innerHTML = icono +
                    'Seleccionados: ' + seleccionados + ' de ' + limite +
                    '. Podés elegir ' + (limite - seleccionados) +
                    ' asiento(s) más.';
            }
        }
    }

    asientos.forEach(function (asiento) {
        asiento.addEventListener('change', actualizarAsientos);
    });

    actualizarAsientos();
});
</script>

<?php require_once __DIR__ . '/../../Includes/footer.php'; ?>