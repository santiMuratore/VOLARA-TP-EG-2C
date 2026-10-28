<?php
require_once __DIR__ . '/functions.php';

function promocionVigente(int $codAerolinea, string $destino, ?string $fecha = null): ?array
{
    $fecha = $fecha ?? date('Y-m-d');

    $conexion = conectarDB();
    $sql = "
        SELECT codPromocion, descuento, descripcion
        FROM promociones
        WHERE codAerolinea = :codAerolinea
          AND estado = 'aprobada'
          AND (destino IS NULL OR destino = :destino)
          AND (fechaDesde IS NULL OR fechaDesde <= :fechaDesde)
          AND (fechaHasta IS NULL OR fechaHasta >= :fechaHasta)
        ORDER BY descuento DESC
        LIMIT 1
    ";
    $consulta = $conexion->prepare($sql);
    $consulta->execute([
        'codAerolinea' => $codAerolinea,
        'destino'      => $destino,
        'fechaDesde'   => $fecha,
        'fechaHasta'   => $fecha,
    ]);
    $promocion = $consulta->fetch(PDO::FETCH_ASSOC);

    return $promocion ?: null;
}

function precioConDescuento(float $precio, ?array $promocion): float
{
    if ($promocion === null) {
        return $precio;
    }
    return round($precio * (1 - ((float) $promocion['descuento']) / 100), 2);
}

function textoVigencia(?string $desde, ?string $hasta): string
{
    if ($desde === null && $hasta === null) {
        return 'Sin límite de fechas';
    }
    if ($desde !== null && $hasta !== null) {
        return 'Del ' . date('d/m/Y', strtotime($desde)) . ' al ' . date('d/m/Y', strtotime($hasta));
    }
    if ($desde !== null) {
        return 'Desde el ' . date('d/m/Y', strtotime($desde));
    }
    return 'Hasta el ' . date('d/m/Y', strtotime($hasta));
}