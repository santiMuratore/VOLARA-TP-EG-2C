-- Contraseña de prueba para TODOS los usuarios:
-- Prueba123
-- ============================================================

USE volara_db;

SET NAMES utf8mb4;

-- Hash generado con:
-- password_hash('Prueba123', PASSWORD_DEFAULT)
SET @clavePrueba = '$2y$12$dRCdvs6gNc0Zm64SxnYMeuThzndJwDRaT8FzQE.e/6Vag2.dINVtS';


-- ============================================================
-- 1. AEROLÍNEAS
-- ============================================================

INSERT INTO aerolineas (codigoIATA, nombre, pais)
VALUES
    ('VLA', 'Volara Airlines', 'Argentina'),
    ('SUR', 'AeroSur Patagónica', 'Argentina');

SET @aerolineaVolara = (
    SELECT codAerolinea
    FROM aerolineas
    WHERE codigoIATA = 'VLA'
);

SET @aerolineaSur = (
    SELECT codAerolinea
    FROM aerolineas
    WHERE codigoIATA = 'SUR'
);


-- ============================================================
-- 2. USUARIOS
-- 1 administrador
-- 2 CEOs (uno por aerolínea)
-- 3 usuarios/pasajeros
-- ============================================================

INSERT INTO usuarios
    (nombre, apellido, mail, clave, telefono, tipoUsuario, codAerolinea, estadoUsuario)
VALUES
    (
        'Admin',
        'Volara',
        'admin@volara.test',
        @clavePrueba,
        '3410000001',
        'administrador',
        NULL,
        'activo'
    ),
    (
        'Carla',
        'Méndez',
        'ceo.volara@volara.test',
        @clavePrueba,
        '3410000002',
        'ceo aerolínea',
        @aerolineaVolara,
        'activo'
    ),
    (
        'Martín',
        'Suárez',
        'ceo.sur@volara.test',
        @clavePrueba,
        '3410000003',
        'ceo aerolínea',
        @aerolineaSur,
        'pendiente de autorización'
    ),
    (
        'Lucía',
        'Fernández',
        'lucia@volara.test',
        @clavePrueba,
        '3410000004',
        'usuario',
        NULL,
        'activo'
    ),
    (
        'Tomás',
        'Pérez',
        'tomas@volara.test',
        @clavePrueba,
        '3410000005',
        'usuario',
        NULL,
        'pendiente de validación'
    ),
    (
        'Sofía',
        'Gómez',
        'sofia@volara.test',
        @clavePrueba,
        '3410000006',
        'usuario',
        NULL,
        'rechazado'
    );

SET @usuarioLucia = (
    SELECT codUsuario
    FROM usuarios
    WHERE mail = 'lucia@volara.test'
);

SET @usuarioTomas = (
    SELECT codUsuario
    FROM usuarios
    WHERE mail = 'tomas@volara.test'
);

SET @usuarioSofia = (
    SELECT codUsuario
    FROM usuarios
    WHERE mail = 'sofia@volara.test'
);


-- ============================================================
-- 3. VUELOS
-- 10 vuelos en total
-- ============================================================

INSERT INTO vuelos
    (
        codigoVuelo,
        codAerolinea,
        origen,
        destino,
        fecha,
        hora,
        precio,
        asientosTotal,
        asientosDisponibles,
        clase
    )
VALUES
    ('VLA100', @aerolineaVolara, 'Rosario',        'Buenos Aires',  '2026-11-05', '08:30:00', 120000.00, 180, 179, 'economica'),
    ('VLA101', @aerolineaVolara, 'Buenos Aires',   'Córdoba',       '2026-11-10', '11:15:00', 150000.00, 180, 180, 'economica'),
    ('VLA102', @aerolineaVolara, 'Rosario',        'Mendoza',       '2026-11-18', '16:40:00',  95000.00, 150, 149, 'premium'),
    ('VLA103', @aerolineaVolara, 'Buenos Aires',   'Salta',         '2026-12-02', '07:20:00', 175000.00, 180, 180, 'economica'),
    ('VLA104', @aerolineaVolara, 'Córdoba',        'Bariloche',     '2026-12-15', '13:50:00', 210000.00, 160, 160, 'business'),

    ('SUR200', @aerolineaSur,    'Buenos Aires',   'Ushuaia',       '2026-11-08', '06:45:00', 110000.00, 140, 139, 'economica'),
    ('SUR201', @aerolineaSur,    'Rosario',        'Neuquén',       '2026-11-22', '18:10:00', 140000.00, 160, 159, 'premium'),
    ('SUR202', @aerolineaSur,    'Mendoza',        'Buenos Aires',  '2026-12-07', '10:00:00', 125000.00, 180, 180, 'economica'),
    ('SUR203', @aerolineaSur,    'Salta',          'Rosario',       '2026-12-20', '20:30:00', 135000.00, 150, 150, 'economica'),
    ('SUR204', @aerolineaSur,    'Bariloche',      'Córdoba',       '2027-01-12', '09:25:00',  90000.00, 130, 130, 'business');

SET @vueloVLA100 = (
    SELECT codVuelo FROM vuelos WHERE codigoVuelo = 'VLA100'
);

SET @vueloVLA101 = (
    SELECT codVuelo FROM vuelos WHERE codigoVuelo = 'VLA101'
);

SET @vueloVLA102 = (
    SELECT codVuelo FROM vuelos WHERE codigoVuelo = 'VLA102'
);

SET @vueloSUR200 = (
    SELECT codVuelo FROM vuelos WHERE codigoVuelo = 'SUR200'
);

SET @vueloSUR201 = (
    SELECT codVuelo FROM vuelos WHERE codigoVuelo = 'SUR201'
);

SET @vueloSUR204 = (
    SELECT codVuelo FROM vuelos WHERE codigoVuelo = 'SUR204'
);


-- ============================================================
-- 4. PROMOCIONES
-- Una aprobada, una pendiente y una denegada
-- ============================================================

INSERT INTO promociones
    (codAerolinea, descripcion, descuento, estado)
VALUES
    (
        @aerolineaVolara,
        '15% de descuento en vuelos seleccionados',
        15.00,
        'aprobada'
    ),
    (
        @aerolineaVolara,
        '10% de descuento para temporada de verano',
        10.00,
        'pendiente'
    ),
    (
        @aerolineaSur,
        '20% de descuento promocional',
        20.00,
        'denegada'
    );

SET @promoVolaraAprobada = (
    SELECT codPromocion
    FROM promociones
    WHERE codAerolinea = @aerolineaVolara
      AND estado = 'aprobada'
    LIMIT 1
);


-- ============================================================
-- 5. NOVEDADES
-- ============================================================

INSERT INTO novedades
    (titulo, texto, fechaPublicacion, fechaExpiracion)
VALUES
    (
        'Nuevos destinos disponibles',
        'VOLARA incorpora nuevos vuelos nacionales para la temporada de verano.',
        '2026-10-01',
        '2026-12-31'
    ),
    (
        'Promociones de noviembre',
        'Consultá las promociones disponibles para vuelos seleccionados durante noviembre.',
        '2026-10-15',
        '2026-11-30'
    ),
    (
        'Recomendaciones para viajar',
        'Recordá presentarte con anticipación y verificar la documentación necesaria para tu viaje.',
        '2026-10-05',
        '2027-01-31'
    );


-- ============================================================
-- 6. RESERVAS
-- 6 reservas en distintos estados
--
-- Coherencia con asientosDisponibles:
-- - pendientes y confirmadas ocupan asiento
-- - canceladas ya devolvieron el asiento
-- ============================================================

INSERT INTO reservas
    (
        codUsuario,
        codVuelo,
        codPromocion,
        estadoReserva,
        precioFinal,
        fechaReserva,
        fechaPago,
        fechaCancelacion
    )
VALUES
    (
        @usuarioLucia,
        @vueloVLA100,
        @promoVolaraAprobada,
        'confirmada',
        102000.00,
        '2026-10-01 10:30:00',
        '2026-10-01 10:45:00',
        NULL
    ),
    (
        @usuarioLucia,
        @vueloVLA102,
        NULL,
        'pendiente de pago',
        95000.00,
        '2026-10-04 14:20:00',
        NULL,
        NULL
    ),
    (
        @usuarioTomas,
        @vueloVLA101,
        @promoVolaraAprobada,
        'cancelada',
        127500.00,
        '2026-10-03 09:10:00',
        NULL,
        '2026-10-05 12:00:00'
    ),
    (
        @usuarioTomas,
        @vueloSUR200,
        NULL,
        'confirmada',
        110000.00,
        '2026-10-06 16:00:00',
        '2026-10-06 16:12:00',
        NULL
    ),
    (
        @usuarioSofia,
        @vueloSUR201,
        NULL,
        'pendiente de pago',
        140000.00,
        '2026-10-08 11:35:00',
        NULL,
        NULL
    ),
    (
        @usuarioSofia,
        @vueloSUR204,
        NULL,
        'cancelada',
        90000.00,
        '2026-10-09 18:40:00',
        NULL,
        '2026-10-10 08:15:00'
    );


-- ============================================================
-- CREDENCIALES DE PRUEBA
-- ============================================================
--
-- Todos usan la misma contraseña:
--   Prueba123
--
-- Administrador:
--   admin@volara.test
--
-- CEO activo:
--   ceo.volara@volara.test
--
-- CEO pendiente de autorización:
--   ceo.sur@volara.test
--
-- Usuario activo:
--   lucia@volara.test
--
-- Usuario pendiente de validación:
--   tomas@volara.test
--
-- Usuario rechazado:
--   sofia@volara.test
