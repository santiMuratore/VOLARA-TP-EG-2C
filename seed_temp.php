<?php
$host = 'localhost';
$usuario = 'root';
$clave = '';

$pdo = new PDO("mysql:host=$host", $usuario, $clave);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("USE volara_db");

// Hash de prueba
$clavePrueba = '$2y$12$dRCdvs6gNc0Zm64SxnYMeuThzndJwDRaT8FzQE.e/6Vag2.dINVtS';

// Insertar aerolíneas
$pdo->exec("INSERT INTO aerolineas (codigoIATA, nombre, pais) VALUES ('VLA', 'Volara Airlines', 'Argentina')");
$pdo->exec("INSERT INTO aerolineas (codigoIATA, nombre, pais) VALUES ('SUR', 'AeroSur Patagónica', 'Argentina')");

// Obtener IDs
$volaraResult = $pdo->query("SELECT codAerolinea FROM aerolineas WHERE codigoIATA = 'VLA'");
$volaraRow = $volaraResult->fetch(PDO::FETCH_ASSOC);
$volaraId = $volaraRow['codAerolinea'];

$surResult = $pdo->query("SELECT codAerolinea FROM aerolineas WHERE codigoIATA = 'SUR'");
$surRow = $surResult->fetch(PDO::FETCH_ASSOC);
$surId = $surRow['codAerolinea'];

// Insertar usuarios
$pdo->exec("INSERT INTO usuarios (nombre, apellido, mail, clave, telefono, tipoUsuario, codAerolinea, estadoUsuario) VALUES ('Admin', 'Volara', 'admin@volara.test', '$clavePrueba', '3410000001', 'administrador', NULL, 'activo')");
$pdo->exec("INSERT INTO usuarios (nombre, apellido, mail, clave, telefono, tipoUsuario, codAerolinea, estadoUsuario) VALUES ('Carla', 'Méndez', 'ceo.volara@volara.test', '$clavePrueba', '3410000002', 'ceo aerolínea', $volaraId, 'activo')");
$pdo->exec("INSERT INTO usuarios (nombre, apellido, mail, clave, telefono, tipoUsuario, codAerolinea, estadoUsuario) VALUES ('Martín', 'Suárez', 'ceo.sur@volara.test', '$clavePrueba', '3410000003', 'ceo aerolínea', $surId, 'pendiente de autorización')");
$pdo->exec("INSERT INTO usuarios (nombre, apellido, mail, clave, telefono, tipoUsuario, codAerolinea, estadoUsuario) VALUES ('Lucía', 'Fernández', 'lucia@volara.test', '$clavePrueba', '3410000004', 'usuario', NULL, 'activo')");
$pdo->exec("INSERT INTO usuarios (nombre, apellido, mail, clave, telefono, tipoUsuario, codAerolinea, estadoUsuario) VALUES ('Tomás', 'Pérez', 'tomas@volara.test', '$clavePrueba', '3410000005', 'usuario', NULL, 'pendiente de validación')");
$pdo->exec("INSERT INTO usuarios (nombre, apellido, mail, clave, telefono, tipoUsuario, codAerolinea, estadoUsuario) VALUES ('Sofía', 'Gómez', 'sofia@volara.test', '$clavePrueba', '3410000006', 'usuario', NULL, 'rechazado')");

// Insertar vuelos
$pdo->exec("INSERT INTO vuelos (codigoVuelo, codAerolinea, origen, destino, fecha, hora, precio, asientosTotal, asientosDisponibles, clase) VALUES ('VLA100', $volaraId, 'Rosario', 'Buenos Aires', '2026-11-05', '08:30:00', 120000.00, 180, 179, 'economica')");
$pdo->exec("INSERT INTO vuelos (codigoVuelo, codAerolinea, origen, destino, fecha, hora, precio, asientosTotal, asientosDisponibles, clase) VALUES ('VLA101', $volaraId, 'Buenos Aires', 'Córdoba', '2026-11-10', '11:15:00', 150000.00, 180, 180, 'economica')");
$pdo->exec("INSERT INTO vuelos (codigoVuelo, codAerolinea, origen, destino, fecha, hora, precio, asientosTotal, asientosDisponibles, clase) VALUES ('VLA102', $volaraId, 'Rosario', 'Mendoza', '2026-11-18', '16:40:00', 95000.00, 150, 149, 'premium')");
$pdo->exec("INSERT INTO vuelos (codigoVuelo, codAerolinea, origen, destino, fecha, hora, precio, asientosTotal, asientosDisponibles, clase) VALUES ('VLA103', $volaraId, 'Buenos Aires', 'Salta', '2026-12-02', '07:20:00', 175000.00, 180, 180, 'economica')");
$pdo->exec("INSERT INTO vuelos (codigoVuelo, codAerolinea, origen, destino, fecha, hora, precio, asientosTotal, asientosDisponibles, clase) VALUES ('VLA104', $volaraId, 'Córdoba', 'Bariloche', '2026-12-15', '13:50:00', 210000.00, 160, 160, 'business')");
$pdo->exec("INSERT INTO vuelos (codigoVuelo, codAerolinea, origen, destino, fecha, hora, precio, asientosTotal, asientosDisponibles, clase) VALUES ('SUR200', $surId, 'Buenos Aires', 'Ushuaia', '2026-11-08', '06:45:00', 110000.00, 140, 139, 'economica')");
$pdo->exec("INSERT INTO vuelos (codigoVuelo, codAerolinea, origen, destino, fecha, hora, precio, asientosTotal, asientosDisponibles, clase) VALUES ('SUR201', $surId, 'Rosario', 'Neuquén', '2026-11-22', '18:10:00', 140000.00, 160, 159, 'premium')");
$pdo->exec("INSERT INTO vuelos (codigoVuelo, codAerolinea, origen, destino, fecha, hora, precio, asientosTotal, asientosDisponibles, clase) VALUES ('SUR202', $surId, 'Mendoza', 'Buenos Aires', '2026-12-07', '10:00:00', 125000.00, 180, 180, 'economica')");
$pdo->exec("INSERT INTO vuelos (codigoVuelo, codAerolinea, origen, destino, fecha, hora, precio, asientosTotal, asientosDisponibles, clase) VALUES ('SUR203', $surId, 'Salta', 'Rosario', '2026-12-20', '20:30:00', 135000.00, 150, 150, 'economica')");
$pdo->exec("INSERT INTO vuelos (codigoVuelo, codAerolinea, origen, destino, fecha, hora, precio, asientosTotal, asientosDisponibles, clase) VALUES ('SUR204', $surId, 'Bariloche', 'Córdoba', '2027-01-12', '09:25:00', 90000.00, 130, 130, 'business')");

// Insertar promociones
$pdo->exec("INSERT INTO promociones (codAerolinea, descripcion, descuento, estado) VALUES ($volaraId, '15% de descuento en vuelos seleccionados', 15.00, 'aprobada')");
$pdo->exec("INSERT INTO promociones (codAerolinea, descripcion, descuento, estado) VALUES ($volaraId, '10% de descuento para temporada de verano', 10.00, 'pendiente')");
$pdo->exec("INSERT INTO promociones (codAerolinea, descripcion, descuento, estado) VALUES ($surId, '20% de descuento promocional', 20.00, 'denegada')");

// Obtener ID de promo aprobada
$promoResult = $pdo->query("SELECT codPromocion FROM promociones WHERE codAerolinea = $volaraId AND estado = 'aprobada' LIMIT 1");
$promoRow = $promoResult->fetch(PDO::FETCH_ASSOC);
$promoId = $promoRow['codPromocion'];

// Insertar novedades
$pdo->exec("INSERT INTO novedades (titulo, texto, fechaPublicacion, fechaExpiracion) VALUES ('Nuevos destinos disponibles', 'VOLARA incorpora nuevos vuelos nacionales para la temporada de verano.', '2026-10-01', '2026-12-31')");
$pdo->exec("INSERT INTO novedades (titulo, texto, fechaPublicacion, fechaExpiracion) VALUES ('Promociones de noviembre', 'Consultá las promociones disponibles para vuelos seleccionados durante noviembre.', '2026-10-15', '2026-11-30')");
$pdo->exec("INSERT INTO novedades (titulo, texto, fechaPublicacion, fechaExpiracion) VALUES ('Recomendaciones para viajar', 'Recordá presentarte con anticipación y verificar la documentación necesaria para tu viaje.', '2026-10-05', '2027-01-31')");

// Insertar reservas (usando IDs reales: 1=admin, 2=ceo.volara, 3=ceo.sur, 4=lucia, 5=tomas, 6=sofia)
$vueloVLA100 = 1;
$vueloVLA101 = 2;
$vueloVLA102 = 3;
$vueloSUR200 = 4;
$vueloSUR201 = 5;
$vueloSUR202 = 6;
$vueloSUR203 = 7;
$vueloSUR204 = 8;

$pdo->exec("INSERT INTO reservas (codUsuario, codVuelo, codPromocion, estadoReserva, precioFinal, fechaReserva, fechaPago, fechaCancelacion) VALUES (4, $vueloVLA100, $promoId, 'confirmada', 102000.00, '2026-10-01 10:30:00', '2026-10-01 10:45:00', NULL)");
$pdo->exec("INSERT INTO reservas (codUsuario, codVuelo, codPromocion, estadoReserva, precioFinal, fechaReserva, fechaPago, fechaCancelacion) VALUES (5, $vueloVLA102, NULL, 'pendiente de pago', 95000.00, '2026-10-04 14:20:00', NULL, NULL)");
$pdo->exec("INSERT INTO reservas (codUsuario, codVuelo, codPromocion, estadoReserva, precioFinal, fechaReserva, fechaPago, fechaCancelacion) VALUES (6, $vueloVLA101, $promoId, 'cancelada', 127500.00, '2026-10-03 09:10:00', NULL, '2026-10-05 12:00:00')");
$pdo->exec("INSERT INTO reservas (codUsuario, codVuelo, codPromocion, estadoReserva, precioFinal, fechaReserva, fechaPago, fechaCancelacion) VALUES (5, $vueloSUR200, NULL, 'confirmada', 110000.00, '2026-10-06 16:00:00', '2026-10-06 16:12:00', NULL)");
$pdo->exec("INSERT INTO reservas (codUsuario, codVuelo, codPromocion, estadoReserva, precioFinal, fechaReserva, fechaPago, fechaCancelacion) VALUES (6, 5, NULL, 'pendiente de pago', 140000.00, '2026-10-08 11:35:00', NULL, NULL)");
$pdo->exec("INSERT INTO reservas (codUsuario, codVuelo, codPromocion, estadoReserva, precioFinal, fechaReserva, fechaPago, fechaCancelacion) VALUES (6, $vueloSUR204, NULL, 'cancelada', 90000.00, '2026-10-09 18:40:00', NULL, '2026-10-10 08:15:00')");

echo 'Seed aplicado exitosamente!';