<?php
    require_once __DIR__ . '/../../Includes/functions.php';
    requerirRol('ceo aerolínea');
    $conexion = conectarDB();
    $titulo = "Vuelos";

    $errores = [];

    // Mensaje que dejó eliminarVuelo.php (se muestra una sola vez)
    $flash = $_SESSION['flash_vuelos'] ?? null;
    unset($_SESSION['flash_vuelos']);
    $clases = ['economica', 'premium', 'business'];

    if (isset($_POST["enviar"])) {

        $tokenRecibido =
            $_POST['csrf_token'] ?? null;

        if (
            !is_string($tokenRecibido) ||
            !csrfValido($tokenRecibido)
        ) {
            $errores['general'] =
                'La solicitud no es válida. Recargá la página e intentá nuevamente.';
        }

        $origen = trim($_POST['origen'] ?? '');
        $destino = trim($_POST['destino'] ?? '');
        $fecha = $_POST['fecha'] ?? '';
        $hora = $_POST['hora'] ?? '';
        $precio = $_POST['precio'] ?? '';
        $cantidad = $_POST['cantidad'] ?? '';
        $clase = $_POST['clase'] ?? '';

        //Validaciones del servidor
        if ($origen === '') {
            $errores['origen'] = 'Complete el origen.';
        }
        if ($destino === '') {
            $errores['destino'] = 'Complete el destino.';
        } elseif (mb_strtolower($origen) === mb_strtolower($destino)) {
            $errores['destino'] = 'El destino debe ser distinto del origen.';
        }
        if ($fecha === '' || strtotime($fecha) === false) {
            $errores['fecha'] = 'Ingrese una fecha válida.';
        } elseif ($fecha <= date('Y-m-d')) {
            $errores['fecha'] = 'La fecha debe ser futura.';
        }
        if ($hora === '') {
            $errores['hora'] = 'Ingrese la hora.';
        }
        if (!is_numeric($precio) || $precio <= 0) {
            $errores['precio'] = 'El precio debe ser mayor a cero.';
        }
        if (filter_var($cantidad, FILTER_VALIDATE_INT) === false || $cantidad <= 0) {
            $errores['cantidad'] = 'La cantidad de asientos debe ser mayor a cero.';
        }
        if (!in_array($clase, $clases, true)) {
            $errores['clase'] = 'Seleccione una clase válida.';
        }

        // Solo si no hay errores se inserta
        if (empty($errores)) {
            //Buscar código IATA
            $codAerolinea = $_SESSION['codAerolinea'];
            $consulta = $conexion->prepare("SELECT codigoIATA FROM aerolineas WHERE codAerolinea = :cod");
            $consulta->execute(['cod' => $codAerolinea]);
            $iata = $consulta->fetchColumn();

            //Buscar Últimos 3 dígitos
            $consulta = $conexion->prepare("SELECT MAX(CAST(SUBSTRING(codigoVuelo, 4) AS UNSIGNED)) FROM vuelos WHERE codAerolinea = :cod");
            $consulta->execute(['cod' => $codAerolinea]);
            $ultimo = (int) $consulta->fetchColumn();   // MAX da NULL si no hay vuelos; (int) lo vuelve 0

            //Armar códigoVuelo
            $codigoVuelo = $iata . sprintf('%03d', $ultimo + 1);    //sprintf relleno con 0. '%03d' rellena con 0 hasta 3 dígitos.

            //Insertar Vuelo a DB.
            $sql = "INSERT INTO vuelos (codigoVuelo, codAerolinea, origen, destino, fecha, hora, precio, asientosTotal, asientosDisponibles, clase)
                    VALUES (:codigoVuelo, :codAerolinea, :origen, :destino, :fecha, :hora, :precio, :asientos, :asientos, :clase)";
            try {
                $consulta = $conexion->prepare($sql);
                $consulta->execute(['codigoVuelo' => $codigoVuelo,
                                    'codAerolinea' => $codAerolinea,
                                    'origen' => $origen,
                                    'destino' => $destino,
                                    'fecha' => $fecha,
                                    'hora' => $hora,
                                    'precio' => $precio,
                                    'asientos' => $cantidad,
                                    'clase' => $clase]);
                // Post/Redirect/Get: se redirige para que F5 no vuelva a enviar el formulario
                $_SESSION['flash_vuelos'] = ['tipo' => 'success', 'texto' => "Vuelo $codigoVuelo creado correctamente."];
                header('Location: vuelos.php');
                exit;
            } catch (PDOException $e) {
                $errores['general'] = 'No se pudo guardar el vuelo.';
            }
        }
    }

    // Vuelos de la aerolínea del CEO logueado (después del INSERT, así aparece el nuevo)
    $codAerolineaActual = $_SESSION['codAerolinea'] ?? null;
    $porPagina = 5;

    // Total de vuelos, para calcular cuántas páginas hay
    $consulta = $conexion->prepare("SELECT COUNT(*) FROM vuelos WHERE codAerolinea = :cod AND activo = 1");
    $consulta->execute(['cod' => $codAerolineaActual]);
    $totalVuelos = (int) $consulta->fetchColumn();
    $totalPaginas = max(1, (int) ceil($totalVuelos / $porPagina));

    // Página pedida por la URL (?pagina=2), limitada al rango válido
    $pagina = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT) ?: 1;
    $pagina = min(max($pagina, 1), $totalPaginas);
    $desde = ($pagina - 1) * $porPagina;

    $consulta = $conexion->prepare("SELECT codVuelo, codigoVuelo, origen, destino, fecha, hora, precio, asientosTotal, asientosDisponibles, clase
                                    FROM vuelos
                                    WHERE codAerolinea = :cod AND activo = 1
                                    ORDER BY fecha, hora
                                    LIMIT :limite OFFSET :desde");
    $consulta->bindValue(':cod', $codAerolineaActual);
    $consulta->bindValue(':limite', $porPagina, PDO::PARAM_INT);   // LIMIT/OFFSET exigen enteros
    $consulta->bindValue(':desde', $desde, PDO::PARAM_INT);
    $consulta->execute();
    $vuelos = $consulta->fetchAll(PDO::FETCH_ASSOC);

    $nombresClase = ['economica' => 'Económica', 'premium' => 'Premium', 'business' => 'Business'];

    // Confirmación de baja: ?eliminar=<codVuelo> muestra la tarjeta de confirmación (sin JavaScript)
    $vueloAEliminar = false;
    $codConfirmar = filter_input(INPUT_GET, 'eliminar', FILTER_VALIDATE_INT) ?: 0;
    if ($codConfirmar > 0) {
        $consulta = $conexion->prepare("SELECT codVuelo, codigoVuelo, origen, destino FROM vuelos
                                        WHERE codVuelo = :codVuelo AND codAerolinea = :cod AND activo = 1");
        $consulta->execute(['codVuelo' => $codConfirmar, 'cod' => $codAerolineaActual]);
        $vueloAEliminar = $consulta->fetch(PDO::FETCH_ASSOC);
    }

    require_once __DIR__ . '/../../Includes/header.php';
    require_once __DIR__ . '/../../Includes/navbar.php';
?>
<main>
    <div class="container py-4">
        <div class="row g-4">

            <div class="col-lg-5">
                <div class="volara-card">
                    <h2 class="h4">Nuevo Vuelo</h2>

                    <?php if ($flash): ?>
                        <div class="volara-alert alert-<?= $flash['tipo'] === 'success' ? 'success' : 'danger' ?>"><?= htmlspecialchars($flash['texto']) ?></div>
                    <?php endif; ?>
                    <?php if (isset($errores['general'])): ?>
                        <div class="volara-alert alert-danger"><?= htmlspecialchars($errores['general']) ?></div>
                    <?php endif; ?>

                    <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]) ?>" method="post">
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars(
                                csrfToken(),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >

                        <!-- Fila 1: origen y destino -->
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="volara-label" for="origen">Origen *</label>
                                <input class="volara-input <?= claseInvalida($errores, 'origen') ?>" type="text" id="origen" name="origen" maxlength="100"
                                       value="<?= htmlspecialchars($_POST['origen'] ?? '') ?>" required>
                                <?php if (isset($errores['origen'])): ?>
                                    <div class="form-error"><?= htmlspecialchars($errores['origen']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6 form-group">
                                <label class="volara-label" for="destino">Destino *</label>
                                <input class="volara-input <?= claseInvalida($errores, 'destino') ?>" type="text" id="destino" name="destino" maxlength="100"
                                       value="<?= htmlspecialchars($_POST['destino'] ?? '') ?>" required>
                                <?php if (isset($errores['destino'])): ?>
                                    <div class="form-error"><?= htmlspecialchars($errores['destino']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Fila 2: fecha y hora -->
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="volara-label" for="fecha">Fecha *</label>
                                <input class="volara-input <?= claseInvalida($errores, 'fecha') ?>" type="date" id="fecha" name="fecha" min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                                       value="<?= htmlspecialchars($_POST['fecha'] ?? '') ?>" required>
                                <?php if (isset($errores['fecha'])): ?>
                                    <div class="form-error"><?= htmlspecialchars($errores['fecha']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6 form-group">
                                <label class="volara-label" for="hora">Hora *</label>
                                <input class="volara-input <?= claseInvalida($errores, 'hora') ?>" type="time" id="hora" name="hora"
                                       value="<?= htmlspecialchars($_POST['hora'] ?? '') ?>" required>
                                <?php if (isset($errores['hora'])): ?>
                                    <div class="form-error"><?= htmlspecialchars($errores['hora']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Fila 3: clase, precio y cantidad de asientos -->
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label class="volara-label" for="clase">Clase *</label>
                                <select class="volara-select <?= claseInvalida($errores, 'clase') ?>" id="clase" name="clase" required>
                                    <option value="economica" <?= ($_POST['clase'] ?? '') === 'economica' ? 'selected' : '' ?>>Económica</option>
                                    <option value="premium" <?= ($_POST['clase'] ?? '') === 'premium' ? 'selected' : '' ?>>Premium</option>
                                    <option value="business" <?= ($_POST['clase'] ?? '') === 'business' ? 'selected' : '' ?>>Business</option>
                                </select>
                                <?php if (isset($errores['clase'])): ?>
                                    <div class="form-error"><?= htmlspecialchars($errores['clase']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-4 form-group">
                                <label class="volara-label" for="precio">Precio *</label>
                                <input class="volara-input <?= claseInvalida($errores, 'precio') ?>" type="number" id="precio" name="precio" min="100000" step="10000"
                                       value="<?= htmlspecialchars($_POST['precio'] ?? 100000) ?>" required>
                                <?php if (isset($errores['precio'])): ?>
                                    <div class="form-error"><?= htmlspecialchars($errores['precio']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-4 form-group">
                                <label class="volara-label" for="cantidad">Asientos *</label>
                                <input class="volara-input <?= claseInvalida($errores, 'cantidad') ?>" type="number" id="cantidad" name="cantidad" min="100" step="10"
                                       value="<?= htmlspecialchars($_POST['cantidad'] ?? 100) ?>" required>
                                <?php if (isset($errores['cantidad'])): ?>
                                    <div class="form-error"><?= htmlspecialchars($errores['cantidad']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <input type="submit" name="enviar" value="Nuevo Vuelo" class="btn-volara">
                    </form>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="volara-card">
                    <h2 class="h4">Vuelos de mi aerolínea</h2>
                    <p class="section-subtitle mb-3"><?= $totalVuelos ?> <?= $totalVuelos === 1 ? 'registro' : 'registros' ?></p>

                    <?php if ($vueloAEliminar): ?>
                        <div class="volara-card-flat text-center mb-3">
                            <div class="empty-state-icon" style="opacity: 1; color: var(--volara-red); margin-bottom: 0.5rem;">
                                <i class="bi bi-trash"></i>
                            </div>
                            <h3 class="h5">Eliminar vuelo</h3>
                            <p class="mb-3">
                                ¿Seguro que querés eliminar el vuelo <strong><?= htmlspecialchars($vueloAEliminar['codigoVuelo']) ?></strong>
                                (<?= htmlspecialchars($vueloAEliminar['origen']) ?> → <?= htmlspecialchars($vueloAEliminar['destino']) ?>)?
                            </p>
                            <form action="eliminarVuelo.php" method="post" class="d-flex gap-2 justify-content-center m-0">
                                <input type="hidden" name="codVuelo" value="<?= (int) $vueloAEliminar['codVuelo'] ?>">
                                <input type="hidden" name="pagina" value="<?= (int) $pagina ?>">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                                <a href="?pagina=<?= (int) $pagina ?>" class="btn-volara-outline btn-volara-sm">Cancelar</a>
                                <button type="submit" class="btn-volara btn-volara-sm">Eliminar</button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($vuelos)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon"><i class="bi bi-airplane"></i></div>
                            <p>Todavía no cargaste vuelos.</p>
                        </div>
                    <?php else: ?>
                        <div class="volara-table-responsive">
                            <table class="volara-table tabla-vuelos">
                                <thead>
                                    <tr>
                                        <th>Vuelo</th>
                                        <th>Ruta</th>
                                        <th>Salida</th>
                                        <th>Disponibilidad</th>
                                        <th>Precio</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($vuelos as $vuelo): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($vuelo['codigoVuelo']) ?></strong><br>
                                                <small><?= htmlspecialchars($nombresClase[$vuelo['clase']] ?? $vuelo['clase']) ?></small>
                                            </td>
                                            <td><?= htmlspecialchars($vuelo['origen']) ?> → <?= htmlspecialchars($vuelo['destino']) ?></td>
                                            <td>
                                                <?= date('d/m/Y', strtotime($vuelo['fecha'])) ?><br>
                                                <small><?= substr($vuelo['hora'], 0, 5) ?></small>
                                            </td>
                                            <td>
                                                <?php if ($vuelo['asientosDisponibles'] > 0): ?>
                                                    <span class="volara-badge badge-approved"><?= (int) $vuelo['asientosDisponibles'] ?> / <?= (int) $vuelo['asientosTotal'] ?></span>
                                                <?php else: ?>
                                                    <span class="volara-badge badge-cancelled">Agotado</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>$<?= number_format($vuelo['precio'], 2, ',', '.') ?></td>
                                            <td>
                                                <div class="table-actions">
                                                    <a href="modificarVuelo.php?id=<?= (int) $vuelo['codVuelo'] ?>" class="table-action-btn" aria-label="Editar vuelo <?= htmlspecialchars($vuelo['codigoVuelo']) ?>" title="Editar"><i class="bi bi-pencil"></i></a>
                                                    <a href="?pagina=<?= (int) $pagina ?>&eliminar=<?= (int) $vuelo['codVuelo'] ?>" class="table-action-btn danger"
                                                       aria-label="Eliminar vuelo <?= htmlspecialchars($vuelo['codigoVuelo']) ?>" title="Eliminar"><i class="bi bi-trash"></i></a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($totalPaginas > 1): ?>
                            <nav class="volara-pagination" aria-label="Paginación de vuelos">
                                <?php if ($pagina > 1): ?>
                                    <a href="?pagina=<?= $pagina - 1 ?>" aria-label="Página anterior"><i class="bi bi-chevron-left"></i></a>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                                    <?php if ($i === $pagina): ?>
                                        <span class="active" aria-current="page"><?= $i ?></span>
                                    <?php else: ?>
                                        <a href="?pagina=<?= $i ?>"><?= $i ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <?php if ($pagina < $totalPaginas): ?>
                                    <a href="?pagina=<?= $pagina + 1 ?>" aria-label="Página siguiente"><i class="bi bi-chevron-right"></i></a>
                                <?php endif; ?>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</main>
<?php
    require_once __DIR__ . '/../../Includes/footer.php';
?>
