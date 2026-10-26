<?php
    require_once __DIR__ . '/../../Includes/functions.php';
    require_once __DIR__ . '/../../Includes/functionsF2.php';   // csrfValido()
    requerirRol('ceo aerolínea');

    // Mensaje que se muestra en vuelos.php después de redirigir
    function avisar(string $tipo, string $texto, int $pagina): void
    {
        $_SESSION['flash_vuelos'] = ['tipo' => $tipo, 'texto' => $texto];
        header('Location: vuelos.php' . ($pagina > 1 ? '?pagina=' . $pagina : ''));
        exit;
    }

    // Solo se elimina por POST (un enlace GET podría dispararse sin querer)
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: vuelos.php');
        exit;
    }

    $pagina = filter_input(INPUT_POST, 'pagina', FILTER_VALIDATE_INT) ?: 1;

    if (!csrfValido($_POST['csrf_token'] ?? null)) {
        avisar('danger', 'La solicitud no es válida. Intentá de nuevo.', $pagina);
    }

    $codVuelo = filter_input(INPUT_POST, 'codVuelo', FILTER_VALIDATE_INT) ?: 0;
    $codAerolinea = $_SESSION['codAerolinea'];

    $conexion = conectarDB();

    try {
        // Transacción: se bloquea la fila del vuelo mientras se chequean las reservas
        $conexion->beginTransaction();

        $consulta = $conexion->prepare("SELECT codigoVuelo FROM vuelos
                                        WHERE codVuelo = :codVuelo AND codAerolinea = :codAerolinea AND activo = 1
                                        FOR UPDATE");
        $consulta->execute(['codVuelo' => $codVuelo, 'codAerolinea' => $codAerolinea]);
        $codigoVuelo = $consulta->fetchColumn();

        if ($codigoVuelo === false) {
            $conexion->rollBack();
            avisar('danger', 'El vuelo no existe o ya fue eliminado.', $pagina);
        }

        // Reservas que impiden eliminar: todas las que no estén canceladas
        $consulta = $conexion->prepare("SELECT COUNT(*) FROM reservas
                                        WHERE codVuelo = :codVuelo AND estadoReserva <> 'cancelada'");
        $consulta->execute(['codVuelo' => $codVuelo]);
        $reservasActivas = (int) $consulta->fetchColumn();

        if ($reservasActivas > 0) {
            $conexion->rollBack();
            avisar('danger', "No se puede eliminar el vuelo $codigoVuelo: tiene $reservasActivas "
                           . ($reservasActivas === 1 ? 'reserva' : 'reservas')
                           . ' sin cancelar. Cuando todas estén canceladas podrás eliminarlo.', $pagina);
        }

        // Baja lógica: el vuelo no se borra, solo se marca como inactivo
        $consulta = $conexion->prepare("UPDATE vuelos SET activo = 0
                                        WHERE codVuelo = :codVuelo AND codAerolinea = :codAerolinea");
        $consulta->execute(['codVuelo' => $codVuelo, 'codAerolinea' => $codAerolinea]);

        $conexion->commit();
        avisar('success', "Vuelo $codigoVuelo eliminado correctamente.", $pagina);

    } catch (PDOException $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        avisar('danger', 'No se pudo eliminar el vuelo.', $pagina);
    }
