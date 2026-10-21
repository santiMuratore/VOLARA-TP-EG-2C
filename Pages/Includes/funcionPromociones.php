<?php

require_once __DIR__ . '/functions.php';

function promocionVigente(int $codAerolinea): ?array
{
    $conexion = conectarDB();

    $sql = "
        SELECT codPromocion, descuento
        FROM promociones
        WHERE codAerolinea = :codAerolinea
          AND estado = 'aprobada'
        LIMIT 1
    ";

    $consulta = $conexion->prepare($sql);

    $consulta->execute([
        'codAerolinea' => $codAerolinea
    ]);

    $promocion = $consulta->fetch(PDO::FETCH_ASSOC);

    return $promocion ?: null;
}