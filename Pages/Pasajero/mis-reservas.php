
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

$buscarArchivo = static function (
    string $nombreArchivo
) use ($directoriosPosibles): ?string {
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
| Cargar funciones
|--------------------------------------------------------------------------
*/

$archivoFunciones = $buscarArchivo('functions.php');
$archivoFuncionesF2 = $buscarArchivo('functionsF2.php');

if ($archivoFunciones === null || $archivoFuncionesF2 === null) {
    error_log(
        'VOLARA: no se encontraron functions.php y/o functionsF2.php.'
    );

    http_response_code(500);
    exit(
        'Error de configuración. Verificá la ubicación de los archivos Includes.'
    );
}

require_once $archivoFunciones;
require_once $archivoFuncionesF2;

/*
|--------------------------------------------------------------------------
| Verificar la sesión y el rol
|--------------------------------------------------------------------------
*/

$baseUrl = rtrim(BASE_URL, '/');

if (empty($_SESSION['codUsuario'])) {
    header('Location: ' . $baseUrl . '/Pages/Publico/login.php');
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

/*
|--------------------------------------------------------------------------
| Consultar las reservas del usuario
|--------------------------------------------------------------------------
*/

try {
    $conexion = conectarDB();

    $sql = "
        SELECT
            r.codReserva,
            r.estadoReserva,
            r.precioFinal,
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
        ORDER BY v.fecha DESC, v.hora DESC
    ";

    $consulta = $conexion->prepare($sql);

    $consulta->execute([
        'codUsuario' => $_SESSION['codUsuario'],
    ]);

    $reservas = $consulta->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error cargando reservas VOLARA: ' . $e->getMessage());

    $error = 'No se pudieron cargar tus reservas. Intentá nuevamente.';
}

/*
|--------------------------------------------------------------------------
| Localizar las plantillas del sitio
|--------------------------------------------------------------------------
*/

$directorioPlantillas = null;

foreach ($directoriosPosibles as $directorio) {
    if (
        is_file($directorio . '/header.php') &&
        is_file($directorio . '/navbar.php') &&
        is_file($directorio . '/footer.php')
    ) {
        $directorioPlantillas = $directorio;
        break;
    }
}

if ($directorioPlantillas === null) {
    error_log(
        'VOLARA: no se encontraron header.php, navbar.php y footer.php.'
    );

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
    <!-- Encabezado -->
    <section class="page-header">
        <div class="container">
            <h1>Mis reservas</h1>

            <p class="text-muted mb-3">
                Consultá los detalles de tus vuelos y el estado de tus reservas.
            </p>

            <nav aria-label="Ruta de navegación">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="<?= htmlspecialchars(
                            $baseUrl . '/Pages/Publico/index.php',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>">
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

    <!-- Contenido -->
    <section class="volara-public-section">
        <div class="container">

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
                        <?= count($reservas) === 1
                            ? 'reserva encontrada'
                            : 'reservas encontradas' ?>
                    </p>
                </div>

                <a
                    href="<?= htmlspecialchars(
                        $baseUrl . '/Pages/Publico/vuelos.php',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    class="btn btn-volara"
                >
                    <i class="bi bi-search me-2" aria-hidden="true"></i>
                    Buscar vuelos
                </a>
            </div>

            <?php if ($error === '' && empty($reservas)): ?>

                <!-- Sin reservas -->
                <div class="volara-card text-center py-5">
                    <div class="stat-icon stat-icon-red mx-auto mb-3">
                        <i
                            class="bi bi-ticket-perforated"
                            aria-hidden="true"
                        ></i>
                    </div>

                    <h2 class="h4">Todavía no tenés reservas</h2>

                    <p class="text-muted mb-4">
                        Cuando reserves un vuelo, vas a poder consultar
                        sus detalles desde esta sección.
                    </p>

                    <a
                        href="<?= htmlspecialchars(
                            $baseUrl . '/Pages/Publico/vuelos.php',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        class="btn btn-volara"
                    >
                        Explorar vuelos
                    </a>
                </div>

            <?php elseif (!empty($reservas)): ?>

                <!-- Listado de reservas -->
                <div class="row g-4">

                    <?php foreach ($reservas as $reserva): ?>

                        <?php
                        $estado = trim(
                            (string) ($reserva['estadoReserva'] ?? 'Sin estado')
                        );

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
                        ?>

                        <div class="col-12 col-lg-6">

                            <article class="volara-card reservation-summary h-100">

                                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">

                                    <div class="min-w-0">
                                        <p class="text-muted small mb-1">
                                            Reserva #<?= htmlspecialchars(
                                                (string) $reserva['codReserva'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </p>

                                        <h3 class="h5 mb-2">
                                            <?= htmlspecialchars(
                                                (string) ($reserva['origen'] ?? 'Origen no disponible'),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                            <i
                                                class="bi bi-arrow-right text-danger mx-1"
                                                aria-hidden="true"
                                            ></i>

                                            <?= htmlspecialchars(
                                                (string) ($reserva['destino'] ?? 'Destino no disponible'),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </h3>

                                        <p class="text-muted small mb-0">
                                            <?= htmlspecialchars(
                                                (string) ($reserva['nombreAerolinea'] ?? 'Aerolínea no disponible'),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </p>
                                    </div>

                                    <span class="badge text-bg-<?= $claseEstado ?> flex-shrink-0">
                                        <?= htmlspecialchars(
                                            $estado,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </span>

                                </div>

                                <hr>

                                <div class="reservation-summary-details">

                                    <p>
                                        <i class="bi bi-airplane me-2" aria-hidden="true"></i>
                                        <strong>Vuelo:</strong>

                                        <?= htmlspecialchars(
                                            (string) ($reserva['codigoVuelo'] ?? 'No disponible'),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </p>

                                    <p>
                                        <i class="bi bi-calendar3 me-2" aria-hidden="true"></i>
                                        <strong>Fecha:</strong>
                                        <?= $fechaVuelo ?>
                                    </p>

                                    <p>
                                        <i class="bi bi-clock me-2" aria-hidden="true"></i>
                                        <strong>Hora:</strong>
                                        <?= htmlspecialchars(
                                            $horaVuelo,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </p>

                                    <p>
                                        <i class="bi bi-grid me-2" aria-hidden="true"></i>
                                        <strong>Clase:</strong>

                                        <?= htmlspecialchars(
                                            (string) ($reserva['clase'] ?? 'No especificada'),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </p>

                                </div>

                                <div class="reservation-summary-price">
                                    <span class="text-muted">
                                        Precio de la reserva
                                    </span>

                                    <strong>
                                        <?php if ($precio !== null): ?>
                                            $<?= number_format(
                                                $precio,
                                                2,
                                                ',',
                                                '.'
                                            ) ?>
                                        <?php else: ?>
                                            No disponible
                                        <?php endif; ?>
                                    </strong>
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
