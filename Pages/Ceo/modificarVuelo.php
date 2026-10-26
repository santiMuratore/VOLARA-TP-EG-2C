<?php
    require_once __DIR__ . '/../../Includes/functions.php';
    requerirRol('ceo aerolínea');
    $conexion = conectarDB();
    $titulo = "Modificar vuelo";

    $zona = new DateTimeZone('America/Argentina/Buenos_Aires');

    // Vuelo a modificar: tiene que existir y ser de la aerolínea del CEO logueado
    $codVuelo = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

    $consulta = $conexion->prepare("SELECT codVuelo, codigoVuelo, origen, destino, fecha, hora, precio, clase
                                    FROM vuelos
                                    WHERE codVuelo = :codVuelo AND codAerolinea = :codAerolinea AND activo = 1");
    $consulta->execute(['codVuelo' => $codVuelo, 'codAerolinea' => $_SESSION['codAerolinea']]);
    $vuelo = $consulta->fetch(PDO::FETCH_ASSOC);

    if (!$vuelo) {
        header('Location: vuelos.php');
        exit;
    }

    $errores = [];
    $exito = '';

    // Un vuelo que ya salió no se puede modificar
    $yaSalio = new DateTime($vuelo['fecha'] . ' ' . $vuelo['hora'], $zona) < new DateTime('now', $zona);

    // Con reservas sin cancelar solo se puede cambiar el precio (ruta y horario quedan bloqueados)
    $consulta = $conexion->prepare("SELECT COUNT(*) FROM reservas
                                    WHERE codVuelo = :codVuelo AND estadoReserva <> 'cancelada'");
    $consulta->execute(['codVuelo' => $codVuelo]);
    $reservasActivas = (int) $consulta->fetchColumn();
    $soloPrecio = $reservasActivas > 0;

    // Valores del formulario: los actuales del vuelo, o lo que se envió
    $origen = $vuelo['origen'];
    $destino = $vuelo['destino'];
    $fecha = $vuelo['fecha'];
    $hora = substr($vuelo['hora'], 0, 5);
    $precio = $vuelo['precio'];

    if (isset($_POST['guardar']) && !$yaSalio) {
        $origen = trim($_POST['origen'] ?? '');
        $destino = trim($_POST['destino'] ?? '');
        $fecha = $_POST['fecha'] ?? '';
        $hora = $_POST['hora'] ?? '';
        $precio = $_POST['precio'] ?? '';

        // Aunque alguien altere el formulario, con reservas se conservan los valores originales
        if ($soloPrecio) {
            $origen = $vuelo['origen'];
            $destino = $vuelo['destino'];
            $fecha = $vuelo['fecha'];
            $hora = substr($vuelo['hora'], 0, 5);
        }

        if ($origen === '') {
            $errores['origen'] = 'Complete el origen.';
        }
        if ($destino === '') {
            $errores['destino'] = 'Complete el destino.';
        } elseif (mb_strtolower($origen) === mb_strtolower($destino)) {
            $errores['destino'] = 'El destino debe ser distinto del origen.';
        }

        $fechaValida = DateTime::createFromFormat('Y-m-d', $fecha);
        $fechaValida = $fechaValida !== false && $fechaValida->format('Y-m-d') === $fecha;
        $horaValida = DateTime::createFromFormat('H:i', $hora);
        $horaValida = $horaValida !== false && $horaValida->format('H:i') === $hora;

        if (!$fechaValida) {
            $errores['fecha'] = 'Ingrese una fecha válida.';
        }
        if (!$horaValida) {
            $errores['hora'] = 'Ingrese una hora válida.';
        }

        // Reprogramación: la nueva salida debe ser al menos 24 hs después de este momento.
        // Solo se exige si se cambió la fecha o la hora (cambiar el precio no reprograma).
        if ($fechaValida && $horaValida) {
            $cambioHorario = ($fecha !== $vuelo['fecha']) || ($hora !== substr($vuelo['hora'], 0, 5));

            if ($cambioHorario) {
                $nuevaSalida = new DateTime("$fecha $hora", $zona);
                $minimo = (new DateTime('now', $zona))->modify('+24 hours');

                if ($nuevaSalida < $minimo) {
                    $clave = ($fecha === $vuelo['fecha']) ? 'hora' : 'fecha';
                    $errores[$clave] = 'La salida debe reprogramarse con al menos 24 horas de anticipación (a partir del '
                                     . $minimo->format('d/m/Y H:i') . ').';
                }
            }
        }

        if (!is_numeric($precio) || $precio <= 0) {
            $errores['precio'] = 'El precio debe ser mayor a cero.';
        }

        if (empty($errores)) {
            try {
                $consulta = $conexion->prepare("UPDATE vuelos
                                                SET origen = :origen, destino = :destino,
                                                    fecha = :fecha, hora = :hora, precio = :precio
                                                WHERE codVuelo = :codVuelo AND codAerolinea = :codAerolinea");
                $consulta->execute(['origen' => $origen,
                                    'destino' => $destino,
                                    'fecha' => $fecha,
                                    'hora' => $hora,
                                    'precio' => $precio,
                                    'codVuelo' => $codVuelo,
                                    'codAerolinea' => $_SESSION['codAerolinea']]);

                // El "vuelo" original pasa a ser el recién guardado
                $vuelo['origen'] = $origen;
                $vuelo['destino'] = $destino;
                $vuelo['fecha'] = $fecha;
                $vuelo['hora'] = $hora . ':00';
                $vuelo['precio'] = $precio;
                $exito = 'Vuelo ' . $vuelo['codigoVuelo'] . ' modificado correctamente.';
            } catch (PDOException $e) {
                $errores['general'] = 'No se pudo modificar el vuelo.';
            }
        }
    }

    $nombresClase = ['economica' => 'Económica', 'premium' => 'Premium', 'business' => 'Business'];

    require_once __DIR__ . '/../../Includes/header.php';
    require_once __DIR__ . '/../../Includes/navbar.php';
?>
<main>
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="volara-card">
                    <h2 class="h4">Modificar vuelo <?= htmlspecialchars($vuelo['codigoVuelo']) ?></h2>
                    <p class="section-subtitle mb-3">
                        Clase <?= htmlspecialchars($nombresClase[$vuelo['clase']] ?? $vuelo['clase']) ?>
                    </p>

                    <?php if ($exito !== ''): ?>
                        <div class="volara-alert alert-success"><?= htmlspecialchars($exito) ?></div>
                    <?php endif; ?>
                    <?php if (isset($errores['general'])): ?>
                        <div class="volara-alert alert-danger"><?= htmlspecialchars($errores['general']) ?></div>
                    <?php endif; ?>

                    <?php if ($yaSalio): ?>
                        <div class="volara-alert alert-warning">Este vuelo ya salió y no puede modificarse.</div>
                        <a href="vuelos.php" class="btn-volara-outline">Volver</a>
                    <?php else: ?>

                    <?php if ($soloPrecio): ?>
                        <div class="volara-alert alert-info">
                            Este vuelo tiene <?= $reservasActivas ?> <?= $reservasActivas === 1 ? 'reserva' : 'reservas' ?> sin cancelar:
                            solo se puede modificar el precio.
                        </div>
                    <?php endif; ?>

                    <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?id=<?= (int) $codVuelo ?>" method="post">

                        <!-- Fila 1: origen y destino -->
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="volara-label" for="origen">Origen *</label>
                                <input class="volara-input <?= claseInvalida($errores, 'origen') ?>" type="text" id="origen" name="origen" maxlength="100"
                                       value="<?= htmlspecialchars($origen) ?>" <?= $soloPrecio ? 'readonly' : '' ?> required>
                                <?php if (isset($errores['origen'])): ?>
                                    <div class="form-error"><?= htmlspecialchars($errores['origen']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6 form-group">
                                <label class="volara-label" for="destino">Destino *</label>
                                <input class="volara-input <?= claseInvalida($errores, 'destino') ?>" type="text" id="destino" name="destino" maxlength="100"
                                       value="<?= htmlspecialchars($destino) ?>" <?= $soloPrecio ? 'readonly' : '' ?> required>
                                <?php if (isset($errores['destino'])): ?>
                                    <div class="form-error"><?= htmlspecialchars($errores['destino']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Fila 2: fecha y hora -->
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="volara-label" for="fecha">Fecha *</label>
                                <input class="volara-input <?= claseInvalida($errores, 'fecha') ?>" type="date" id="fecha" name="fecha"
                                       value="<?= htmlspecialchars($fecha) ?>" <?= $soloPrecio ? 'readonly' : '' ?> required>
                                <?php if (isset($errores['fecha'])): ?>
                                    <div class="form-error"><?= htmlspecialchars($errores['fecha']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6 form-group">
                                <label class="volara-label" for="hora">Hora *</label>
                                <input class="volara-input <?= claseInvalida($errores, 'hora') ?>" type="time" id="hora" name="hora"
                                       value="<?= htmlspecialchars($hora) ?>" <?= $soloPrecio ? 'readonly' : '' ?> required>
                                <?php if (isset($errores['hora'])): ?>
                                    <div class="form-error"><?= htmlspecialchars($errores['hora']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Fila 3: precio -->
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="volara-label" for="precio">Precio *</label>
                                <input class="volara-input <?= claseInvalida($errores, 'precio') ?>" type="number" id="precio" name="precio" min="0.01" step="0.01"
                                       value="<?= htmlspecialchars($precio) ?>" required>
                                <?php if (isset($errores['precio'])): ?>
                                    <div class="form-error"><?= htmlspecialchars($errores['precio']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <input type="submit" name="guardar" value="Guardar cambios" class="btn-volara">
                        <a href="vuelos.php" class="btn-volara-outline">Volver</a>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>
<?php
    require_once __DIR__ . '/../../Includes/footer.php';
?>
