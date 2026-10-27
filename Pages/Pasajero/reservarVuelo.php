<?php

require_once __DIR__ . '/../../Includes/functions.php';
require_once __DIR__ . '/../../Includes/functionsF2.php';

if (empty($_SESSION['codUsuario'])) {

    header(
        'Location: '
        . BASE_URL
        . '/Auth/login.php'
    );

    exit;
}
if (
    ($_SESSION['tipoUsuario'] ?? '')
    !== 'usuario'
) {

    header(
        'Location: '
        . BASE_URL
        . '/Pages/Publico/index.php'
    );

    exit;
}

if (empty($_SESSION['csrf_reserva'])) {

    $_SESSION['csrf_reserva'] =
        bin2hex(random_bytes(32));
}

$csrfToken =
    $_SESSION['csrf_reserva'];

$codVuelo = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $codVuelo =
        (int) ($_POST['codVuelo'] ?? 0);

} else {

    $codVuelo =
        (int) ($_GET['codVuelo'] ?? 0);
}


$error = '';
$vuelo = null;
$promocion = null;

$conexion = conectarDB();

if ($codVuelo > 0) {

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

    $consulta =
        $conexion->prepare($sql);

    $consulta->execute([
        'codVuelo' => $codVuelo
    ]);

    $vuelo =
        $consulta->fetch(PDO::FETCH_ASSOC);
}


if (!$vuelo) {

    $error =
        'El vuelo seleccionado no existe.';
}

if ($vuelo) {

    $sql = "
        SELECT
            codPromocion,
            descripcion,
            descuento
        FROM promociones
        WHERE codAerolinea = :codAerolinea
          AND estado = 'aprobada'
        LIMIT 1
    ";

    $consulta =
        $conexion->prepare($sql);

    $consulta->execute([
        'codAerolinea' =>
            $vuelo['codAerolinea']
    ]);

    $promocion =
        $consulta->fetch(PDO::FETCH_ASSOC);

    if (!$promocion) {
        $promocion = null;
    }
}


// ---------------------------------------------------------
// Procesar reserva
// ---------------------------------------------------------

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $vuelo
) {

    $csrfRecibido =
        $_POST['csrf_token'] ?? '';

    if (
        !hash_equals(
            $csrfToken,
            (string) $csrfRecibido
        )
    ) {

        $error =
            'El formulario venció. Recargá la página e intentá nuevamente.';

    } else {

        $usarPromocion =
            isset($_POST['aplicarPromocion']);

        try {

            $conexion->beginTransaction();


            // Bloquear el vuelo mientras se reserva.
            $sql = "
                SELECT
                    codVuelo,
                    codAerolinea,
                    precio,
                    asientosDisponibles,
                    fecha,
                    hora
                FROM vuelos
                WHERE codVuelo = :codVuelo
                AND v.activo = 1
                FOR UPDATE
            ";

            $consulta =
                $conexion->prepare($sql);

            $consulta->execute([
                'codVuelo' => $codVuelo
            ]);

            $vueloActual =
                $consulta->fetch(PDO::FETCH_ASSOC);


            if (!$vueloActual) {

                throw new Exception(
                    'El vuelo ya no está disponible.'
                );
            }


            if (
                (int) $vueloActual['asientosDisponibles']
                <= 0
            ) {

                throw new Exception(
                    'No quedan asientos disponibles para este vuelo.'
                );
            }


            // -------------------------------------------------
            // Calcular promoción y precio final
            // -------------------------------------------------

            $codPromocion = null;

            $precioFinal =
                (float) $vueloActual['precio'];


            if ($usarPromocion) {

                $sql = "
                    SELECT
                        codPromocion,
                        descuento
                    FROM promociones
                    WHERE codAerolinea = :codAerolinea
                      AND estado = 'aprobada'
                    LIMIT 1
                ";

                $consulta =
                    $conexion->prepare($sql);

                $consulta->execute([
                    'codAerolinea' =>
                        $vueloActual['codAerolinea']
                ]);

                $promoActual =
                    $consulta->fetch(PDO::FETCH_ASSOC);


                if ($promoActual) {

                    $codPromocion =
                        (int) $promoActual['codPromocion'];

                    $descuento =
                        (float) $promoActual['descuento'];

                    $precioFinal =
                        $precioFinal
                        * (1 - $descuento / 100);

                    $precioFinal =
                        round($precioFinal, 2);
                }
            }


            // -------------------------------------------------
            // Descontar asiento
            // -------------------------------------------------

            $sql = "
                UPDATE vuelos
                SET asientosDisponibles =
                    asientosDisponibles - 1
                WHERE codVuelo = :codVuelo
                  AND asientosDisponibles > 0
            ";

            $consulta =
                $conexion->prepare($sql);

            $consulta->execute([
                'codVuelo' => $codVuelo
            ]);


            if ($consulta->rowCount() !== 1) {

                throw new Exception(
                    'No quedan asientos disponibles.'
                );
            }


            // -------------------------------------------------
            // Crear reserva
            // -------------------------------------------------

            $sql = "
                INSERT INTO reservas
                (
                    codUsuario,
                    codVuelo,
                    codPromocion,
                    estadoReserva,
                    precioFinal
                )
                VALUES
                (
                    :codUsuario,
                    :codVuelo,
                    :codPromocion,
                    'pendiente de pago',
                    :precioFinal
                )
            ";

            $consulta =
                $conexion->prepare($sql);

            $consulta->execute([
                'codUsuario' =>
                    $_SESSION['codUsuario'],

                'codVuelo' =>
                    $codVuelo,

                'codPromocion' =>
                    $codPromocion,

                'precioFinal' =>
                    $precioFinal
            ]);


            $conexion->commit();


            // Renovar CSRF para impedir reutilización.
            $_SESSION['csrf_reserva'] =
                bin2hex(random_bytes(32));


            header(
                'Location: '
                . BASE_URL
                . '/Pages/Pasajero/mis-reservas.php?creada=1'
            );

            exit;


        } catch (Throwable $e) {

            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }

            error_log(
                'Error reservando vuelo: '
                . $e->getMessage()
            );

            $error = $e->getMessage();
        }
    }
}


// ---------------------------------------------------------
// Calcular precio promocional para mostrarlo
// ---------------------------------------------------------

$precioBase =
    $vuelo
        ? (float) $vuelo['precio']
        : 0;

$precioPromocional = $precioBase;

if ($promocion) {

    $precioPromocional =
        $precioBase
        * (
            1
            - ((float) $promocion['descuento'] / 100)
        );

    $precioPromocional =
        round($precioPromocional, 2);
}


// ---------------------------------------------------------
// Layout
// ---------------------------------------------------------

$titulo = 'Reservar vuelo';

require_once __DIR__
    . '/../../Includes/header.php';

require_once __DIR__
    . '/../../Includes/navbar.php';

?>


<main
    id="contenido-principal"
    tabindex="-1"
>

    <section class="page-header">

        <div class="container">

            <h1>
                Reservar vuelo
            </h1>

            <p class="text-muted mb-0">
                Revisá los datos antes de confirmar tu reserva.
            </p>

        </div>

    </section>


    <section class="section section-gray">

        <div class="container">

            <div class="row justify-content-center">

                <div class="col-12 col-lg-8">

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


                    <?php if ($vuelo): ?>

                        <div class="volara-card">

                            <div
                                class="d-flex justify-content-between
                                       align-items-start
                                       flex-wrap gap-3 mb-4"
                            >

                                <div>

                                    <span class="eyebrow">
                                        <?= htmlspecialchars(
                                            $vuelo['nombreAerolinea'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </span>

                                    <h2 class="h4 mt-2 mb-0">

                                        Vuelo

                                        <?= htmlspecialchars(
                                            $vuelo['codigoVuelo'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </h2>

                                </div>


                                <span
                                    class="volara-badge badge-neutral"
                                >
                                    <?= htmlspecialchars(
                                        ucfirst(
                                            $vuelo['clase']
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>

                            </div>


                            <hr>


                            <div class="row g-4">

                                <div class="col-md-6">

                                    <p class="text-muted mb-1">
                                        Origen
                                    </p>

                                    <h3 class="h5">

                                        <?= htmlspecialchars(
                                            $vuelo['origen'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </h3>

                                </div>


                                <div class="col-md-6">

                                    <p class="text-muted mb-1">
                                        Destino
                                    </p>

                                    <h3 class="h5">

                                        <?= htmlspecialchars(
                                            $vuelo['destino'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </h3>

                                </div>


                                <div class="col-md-4">

                                    <p class="text-muted mb-1">
                                        Fecha
                                    </p>

                                    <strong>

                                        <?= date(
                                            'd/m/Y',
                                            strtotime(
                                                $vuelo['fecha']
                                            )
                                        ) ?>

                                    </strong>

                                </div>


                                <div class="col-md-4">

                                    <p class="text-muted mb-1">
                                        Hora
                                    </p>

                                    <strong>

                                        <?= htmlspecialchars(
                                            substr(
                                                $vuelo['hora'],
                                                0,
                                                5
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </strong>

                                </div>


                                <div class="col-md-4">

                                    <p class="text-muted mb-1">
                                        Asientos disponibles
                                    </p>

                                    <strong>

                                        <?= (int)
                                            $vuelo['asientosDisponibles'] ?>

                                    </strong>

                                </div>

                            </div>


                            <hr class="my-4">


                            <div class="mb-4">

                                <p class="text-muted mb-1">
                                    Precio
                                </p>

                                <h3 class="h3 mb-0">

                                    $<?= number_format(
                                        $precioBase,
                                        2,
                                        ',',
                                        '.'
                                    ) ?>

                                </h3>

                            </div>


                            <form method="post">

                                <input
                                    type="hidden"
                                    name="codVuelo"
                                    value="<?= (int) $vuelo['codVuelo'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars(
                                        $csrfToken,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                >


                                <?php if ($promocion): ?>

                                    <div
                                        class="volara-alert alert-success
                                               align-items-start"
                                    >

                                        <i
                                            class="bi bi-tag-fill mt-1"
                                            aria-hidden="true"
                                        ></i>

                                        <div>

                                            <strong>
                                                Promoción disponible
                                            </strong>

                                            <p class="mb-2">

                                                <?= htmlspecialchars(
                                                    $promocion['descripcion'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                            </p>

                                            <p class="mb-2">

                                                <?= number_format(
                                                    (float)
                                                    $promocion['descuento'],
                                                    0,
                                                    ',',
                                                    '.'
                                                ) ?>% de descuento

                                            </p>

                                            <strong>

                                                Precio con promoción:
                                                $<?= number_format(
                                                    $precioPromocional,
                                                    2,
                                                    ',',
                                                    '.'
                                                ) ?>

                                            </strong>


                                            <div class="form-check mt-3">

                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    id="aplicarPromocion"
                                                    name="aplicarPromocion"
                                                    value="1"
                                                    checked
                                                >

                                                <label
                                                    class="form-check-label"
                                                    for="aplicarPromocion"
                                                >
                                                    Aplicar esta promoción
                                                </label>

                                            </div>

                                        </div>

                                    </div>

                                <?php endif; ?>


                                <?php if (
                                    (int)
                                    $vuelo['asientosDisponibles']
                                    > 0
                                ): ?>

                                    <button
                                        type="submit"
                                        class="btn btn-volara
                                               btn-volara-lg w-100"
                                    >

                                        <i
                                            class="bi bi-ticket-perforated
                                                   me-1"
                                        ></i>

                                        Confirmar reserva

                                    </button>

                                <?php else: ?>

                                    <button
                                        type="button"
                                        class="btn btn-secondary w-100"
                                        disabled
                                    >
                                        Sin asientos disponibles
                                    </button>

                                <?php endif; ?>


                                <a
                                    href="<?= BASE_URL ?>/Pages/Publico/vuelos.php"
                                    class="btn btn-volara-outline w-100 mt-2"
                                >
                                    Volver a vuelos
                                </a>

                            </form>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </section>

</main>


<?php
require_once __DIR__
    . '/../../Includes/footer.php';
?>