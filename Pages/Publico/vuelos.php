<?php
require_once __DIR__ . '/../../Includes/functions.php';
require_once __DIR__ . '/../../Includes/functionsF2.php';

// Configuración de la página.
$titulo = 'Buscar vuelos';
$pageDescription = 'Consultá los vuelos registrados en VOLARA y filtrá por origen, destino, fecha y clase.';

// Valores del buscador.
$origen = trim($_GET['origen'] ?? '');
$destino = trim($_GET['destino'] ?? '');
$fecha = trim($_GET['fecha'] ?? '');

$clase = strtolower(trim($_GET['clase'] ?? ''));

$clasesPermitidas = ['economica', 'premium', 'business'];

if (!in_array($clase, $clasesPermitidas, true)) {
    $clase = '';
}

// Validar el formato de fecha.
if (
    $fecha !== ''
    && (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)
        || !checkdate(
            (int) substr($fecha, 5, 2),
            (int) substr($fecha, 8, 2),
            (int) substr($fecha, 0, 4)
        )
    )
) {
    $fecha = '';
}

$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = 4;

$vuelos = [];
$total = 0;
$error = null;

try {
    $conexion = conectarDB();

    // Construir los filtros.
    $condiciones = [];
    $parametros = [];

    if ($origen !== '') {
        $condiciones[] = 'v.origen LIKE :origen';
        $parametros['origen'] = '%' . $origen . '%';
    }

    if ($destino !== '') {
        $condiciones[] = 'v.destino LIKE :destino';
        $parametros['destino'] = '%' . $destino . '%';
    }

    if ($fecha !== '') {
        $condiciones[] = 'v.fecha = :fecha';
        $parametros['fecha'] = $fecha;
    }

    if ($clase !== '') {
        $condiciones[] = 'LOWER(v.clase) = :clase';
        $parametros['clase'] = $clase;
    }

    $where = $condiciones
        ? ' WHERE ' . implode(' AND ', $condiciones)
        : '';

    $from = "
        FROM vuelos v
        INNER JOIN aerolineas a
            ON v.codAerolinea = a.codAerolinea
    ";

    // Contar los vuelos que coinciden con los filtros.
    $sqlCount = "SELECT COUNT(*) AS total " . $from . $where;

    $stmtCount = $conexion->prepare($sqlCount);
    $stmtCount->execute($parametros);

    $total = (int) $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

    // Calcular la paginación.
    $paginacion = paginar($total, $porPagina, $pagina);

    $pagina = $paginacion['paginaActual'];
    $offset = $paginacion['offset'];

    // Consultar los vuelos de la página actual.
    $sql = "
        SELECT
            v.codigoVuelo,
            v.origen,
            v.destino,
            v.fecha,
            v.hora,
            v.precio,
            v.clase,
            a.nombre AS nombreAerolinea
        " . $from . $where . "
        ORDER BY v.fecha ASC, v.hora ASC
        LIMIT :limite OFFSET :offset
    ";

    $stmt = $conexion->prepare($sql);

    foreach ($parametros as $nombre => $valor) {
        $stmt->bindValue(':' . $nombre, $valor, PDO::PARAM_STR);
    }

    $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();

    $vuelos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Error al consultar vuelos: ' . $e->getMessage());
    $error = 'No se pudieron cargar los vuelos. Intentá nuevamente más tarde.';
}

// Conservar los filtros al cambiar de página.
$filtrosActivos = [];

if ($origen !== '') {
    $filtrosActivos['origen'] = $origen;
}

if ($destino !== '') {
    $filtrosActivos['destino'] = $destino;
}

if ($fecha !== '') {
    $filtrosActivos['fecha'] = $fecha;
}

if ($clase !== '') {
    $filtrosActivos['clase'] = $clase;
}

$urlPaginacion = BASE_URL . '/Pages/Publico/vuelos.php';

if (!empty($filtrosActivos)) {
    $urlPaginacion .= '?' . http_build_query($filtrosActivos);
}

// Encabezado y navbar compartidos.
require __DIR__ . '/../../Includes/header.php';
require __DIR__ . '/../../Includes/navbar.php';
?>

<style>
/* VOLARA: paginación compacta para resultados de vuelos */
.volara-pagination-container {
    display: flex;
    justify-content: center;
    width: 100%;
    margin-bottom: .5rem;
}

.volara-pagination {
    max-width: 100%;
    overflow-x: auto;
}

.volara-pagination .pagination {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    align-items: center;
    gap: .35rem;
    margin: 0;
    padding: 0;
}

.volara-pagination .page-link {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 34px;
    min-height: 34px;
    padding: .35rem .55rem;
    border: 1px solid #DDE2E7;
    border-radius: 8px !important;
    background-color: #FFFFFF;
    color: #374151;
    font-family: inherit;
    font-size: .84rem;
    font-weight: 600;
    line-height: 1.2;
    box-shadow: none;
    transition: background-color .2s ease, color .2s ease,
                border-color .2s ease, transform .2s ease;
}

.volara-pagination .page-link:hover {
    z-index: 1;
    border-color: #E51B23;
    background-color: #FDEAEB;
    color: #C9141C;
}

.volara-pagination .page-item.active .page-link {
    z-index: 2;
    border-color: #E51B23;
    background-color: #E51B23;
    color: #FFFFFF;
    font-weight: 700;
    box-shadow: 0 2px 6px rgba(229, 27, 35, .18);
}

.volara-pagination .page-link:focus-visible {
    z-index: 3;
    outline: 3px solid rgba(229, 27, 35, .25);
    outline-offset: 2px;
    box-shadow: none;
}

.volara-pagination .page-item.disabled .page-link {
    border-color: #E5E7EB;
    background-color: #F4F6F8;
    color: #9CA3AF;
    opacity: .8;
    cursor: not-allowed;
}

@media (max-width: 576px) {
    .volara-pagination .pagination {
        gap: .25rem;
    }

    .volara-pagination .page-link {
        min-width: 30px;
        min-height: 30px;
        padding: .3rem .45rem;
        border-radius: 7px !important;
        font-size: .78rem;
    }
}
</style>

<main id="contenido-principal" tabindex="-1">

    <!-- ENCABEZADO -->
    <section class="page-header">
        <div class="container">

            <h1>Buscar vuelos</h1>

            <p class="text-muted mb-3">
                Encontrá opciones de viaje y consultá sus datos.
            </p>

            <nav aria-label="Ruta de navegación">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="<?= BASE_URL ?>/Pages/Publico/index.php">
                            Inicio
                        </a>
                    </li>

                    <li class="breadcrumb-item active" aria-current="page">
                        Vuelos
                    </li>
                </ol>
            </nav>

        </div>
    </section>

    <!-- BUSCADOR -->
    <section class="section section-gray">
        <div class="container">

            <div class="mb-4">
                <h2 class="section-title">Encontrá tu próximo vuelo</h2>

                <p class="section-subtitle mb-0">
                    Completá los filtros para consultar los vuelos disponibles
                    en el listado.
                </p>
            </div>

            <div class="search-box">

                <form
                    action="<?= BASE_URL ?>/Pages/Publico/vuelos.php"
                    method="GET"
                >

                    <div class="search-fields">

                        <div class="form-group mb-0">
                            <label class="volara-label" for="origen">
                                Origen
                            </label>

                            <input
                                type="text"
                                class="volara-input"
                                id="origen"
                                name="origen"
                                placeholder="Ciudad o aeropuerto"
                                value="<?= htmlspecialchars(
                                    $origen,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                autocomplete="off"
                            >
                        </div>

                        <div class="form-group mb-0">
                            <label class="volara-label" for="destino">
                                Destino
                            </label>

                            <input
                                type="text"
                                class="volara-input"
                                id="destino"
                                name="destino"
                                placeholder="Ciudad o aeropuerto"
                                value="<?= htmlspecialchars(
                                    $destino,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                autocomplete="off"
                            >
                        </div>

                        <div class="form-group mb-0">
                            <label class="volara-label" for="fecha">
                                Fecha de salida
                            </label>

                            <input
                                type="date"
                                class="volara-input"
                                id="fecha"
                                name="fecha"
                                value="<?= htmlspecialchars(
                                    $fecha,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >
                        </div>

                        <div class="form-group mb-0">
                            <label class="volara-label" for="clase">
                                Clase
                            </label>

                            <select
                                class="volara-select"
                                id="clase"
                                name="clase"
                            >
                                <option value="">Todas las clases</option>

                                <option
                                    value="economica"
                                    <?= $clase === 'economica' ? 'selected' : '' ?>
                                >
                                    Económica
                                </option>

                                <option
                                    value="premium"
                                    <?= $clase === 'premium' ? 'selected' : '' ?>
                                >
                                    Premium
                                </option>

                                <option
                                    value="business"
                                    <?= $clase === 'business' ? 'selected' : '' ?>
                                >
                                    Business
                                </option>
                            </select>
                        </div>

                        <div class="search-btn-wrap">
                            <button
                                type="submit"
                                class="btn btn-volara btn-volara-lg w-100"
                            >
                                <i class="bi bi-search" aria-hidden="true"></i>
                                Buscar vuelos
                            </button>
                        </div>

                    </div>

                    <div class="mt-3">
                        <a
                            href="<?= BASE_URL ?>/Pages/Publico/vuelos.php"
                            class="text-muted small"
                        >
                            <i class="bi bi-arrow-counterclockwise me-1"
                               aria-hidden="true"></i>
                            Limpiar filtros
                        </a>
                    </div>

                </form>

            </div>

        </div>
    </section>

    <!-- RESULTADOS -->
    <section class="section">
        <div class="container">

            <div class="d-flex flex-wrap justify-content-between
                        align-items-center gap-3 mb-4">

                <div>
                    <h2 class="section-title">Vuelos registrados</h2>

                    <p class="text-muted mb-0">
                        <?php if ($error === null): ?>
                            <?= $total ?>
                            <?= $total === 1 ? 'vuelo encontrado' : 'vuelos encontrados' ?>
                        <?php else: ?>
                            Resultados no disponibles.
                        <?php endif; ?>
                    </p>
                </div>

                <span class="volara-badge badge-neutral">
                    <i class="bi bi-airplane me-1" aria-hidden="true"></i>
                    VOLARA
                </span>

            </div>

            <?php if ($error !== null): ?>

                <div class="volara-alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-circle" aria-hidden="true"></i>

                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>

            <?php elseif (empty($vuelos)): ?>

                <div class="volara-card-flat text-center">

                    <i
                        class="bi bi-airplane empty-state-icon"
                        aria-hidden="true"
                    ></i>

                    <h3 class="h4">No encontramos vuelos</h3>

                    <p class="text-muted">
                        No hay vuelos que coincidan con los filtros
                        seleccionados.
                    </p>

                    <a
                        href="<?= BASE_URL ?>/Pages/Publico/vuelos.php"
                        class="btn btn-volara"
                    >
                        Ver todos los vuelos
                    </a>

                </div>

            <?php else: ?>

                <div class="row g-4">

                    <?php foreach ($vuelos as $v): ?>

                        <?php
                        $claseVuelo = strtolower((string) ($v['clase'] ?? ''));

                        $nombresClases = [
                            'economica' => 'Económica',
                            'premium' => 'Premium',
                            'business' => 'Business',
                        ];

                        $nombreClase = $nombresClases[$claseVuelo]
                            ?? ucfirst($claseVuelo);

                        $fechaVuelo = !empty($v['fecha'])
                            && strtotime($v['fecha']) !== false
                            ? date('d/m/Y', strtotime($v['fecha']))
                            : 'Sin fecha';

                        $horaVuelo = !empty($v['hora'])
                            ? substr((string) $v['hora'], 0, 5)
                            : '--:--';
                        ?>

                        <div class="col-12 col-lg-6">

                            <article class="flight-card h-100">

                                <!-- Aerolínea y código -->
                                <div class="d-flex flex-wrap
                                            justify-content-between
                                            align-items-start gap-2 mb-4">

                                    <div>
                                        <div class="eyebrow mb-1">
                                            <?= htmlspecialchars(
                                                $v['nombreAerolinea'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </div>

                                        <h3 class="h5 mb-0">
                                            Vuelo
                                            <?= htmlspecialchars(
                                                $v['codigoVuelo'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </h3>
                                    </div>

                                    <span class="volara-badge badge-neutral">
                                        <?= htmlspecialchars(
                                            $nombreClase,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </span>

                                </div>

                                <!-- Ruta -->
                                <div class="flight-route">

                                    <div class="flight-airport">
                                        <div class="code">
                                            <?= htmlspecialchars(
                                                $v['origen'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </div>

                                        <div class="time">
                                            <?= htmlspecialchars(
                                                $horaVuelo,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </div>

                                        <div class="city">Origen</div>
                                    </div>

                                    <div class="flight-line">
                                        <div class="plane-icon">
                                            <i
                                                class="bi bi-airplane"
                                                aria-hidden="true"
                                            ></i>
                                        </div>

                                        <div class="duration">Vuelo</div>
                                    </div>

                                    <div class="flight-airport">
                                        <div class="code">
                                            <?= htmlspecialchars(
                                                $v['destino'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </div>

                                        <div class="time">
                                            <i
                                                class="bi bi-calendar3 me-1"
                                                aria-hidden="true"
                                            ></i>

                                            <?= htmlspecialchars(
                                                $fechaVuelo,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </div>

                                        <div class="city">Destino</div>
                                    </div>

                                </div>

                                <!-- Información -->
                                <div class="flight-meta">

                                    <div class="flight-meta-item">
                                        <i
                                            class="bi bi-calendar-event me-1"
                                            aria-hidden="true"
                                        ></i>

                                        <?= htmlspecialchars(
                                            $fechaVuelo,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </div>

                                    <div class="flight-price">
                                        <div class="amount">
                                            <?= '$' . number_format(
                                                (float) $v['precio'],
                                                0,
                                                ',',
                                                '.'
                                            ) ?>
                                        </div>

                                        <small class="text-muted">
                                            Precio registrado
                                        </small>
                                    </div>

                                </div>

                            </article>

                        </div>

                    <?php endforeach; ?>

                </div>

                <!-- Paginación compacta VOLARA -->
                <?php if ($total > $porPagina): ?>

                    <nav
                        class="volara-pagination-container mt-4"
                        aria-label="Páginas de resultados de vuelos"
                    >
                        <div class="volara-pagination">
                            <?= generarPaginacionHTML(
                                $total,
                                $porPagina,
                                $pagina,
                                $urlPaginacion
                            ) ?>
                        </div>
                    </nav>

                <?php endif; ?>

            <?php endif; ?>

            <!-- Acceso a la cuenta -->
            <?php if (!isset($_SESSION['codUsuario'])): ?>

                <div class="volara-card-flat mt-5">

                    <div class="d-flex flex-column flex-md-row
                                align-items-md-center justify-content-between
                                gap-3">

                        <div>
                            <h3 class="h5 mb-2">
                                ¿Querés continuar con tu viaje?
                            </h3>

                            <p class="text-muted mb-0">
                                Iniciá sesión para acceder a las opciones
                                habilitadas para tu cuenta.
                            </p>
                        </div>

                        <a
                            href="<?= BASE_URL ?>/Auth/login.php"
                            class="btn btn-volara"
                        >
                            <i
                                class="bi bi-person-circle me-1"
                                aria-hidden="true"
                            ></i>

                            Iniciar sesión
                        </a>

                    </div>

                </div>

            <?php endif; ?>

        </div>
    </section>

</main>

<?php require __DIR__ . '/../../Includes/footer.php'; ?>