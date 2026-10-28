SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;


-- ============================================================
-- TABLA: AEROLINEAS
-- ============================================================

CREATE TABLE aerolineas (
    codAerolinea INT UNSIGNED AUTO_INCREMENT,
    codigoIATA CHAR(3) NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    pais VARCHAR(100) NOT NULL,

    PRIMARY KEY (codAerolinea),

    UNIQUE KEY uk_aerolinea_iata (codigoIATA),
    UNIQUE KEY uk_aerolinea_nombre (nombre)

) ENGINE=InnoDB;


-- ============================================================
-- TABLA: USUARIOS
-- ============================================================

CREATE TABLE usuarios (
    codUsuario INT UNSIGNED AUTO_INCREMENT,

    nombre VARCHAR(80) NOT NULL,
    apellido VARCHAR(80) NOT NULL,
    mail VARCHAR(150) NOT NULL,
    clave VARCHAR(255) NOT NULL,
    telefono VARCHAR(30) DEFAULT NULL,

    tipoUsuario ENUM(
        'administrador',
        'ceo aerolínea',
        'usuario'
    ) NOT NULL DEFAULT 'usuario',

    codAerolinea INT UNSIGNED DEFAULT NULL,

    estadoUsuario ENUM(
        'pendiente de validación',
        'pendiente de autorización',
        'activo',
        'rechazado'
    ) NOT NULL DEFAULT 'pendiente de validación',

    tokenValidacion VARCHAR(128) DEFAULT NULL,
    tokenRecuperacion VARCHAR(128) DEFAULT NULL,
    vencimientoToken DATETIME DEFAULT NULL,

    fechaCreacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fechaActualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (codUsuario),

    UNIQUE KEY uk_usuario_mail (mail),
    UNIQUE KEY uk_usuario_token_validacion (tokenValidacion),
    UNIQUE KEY uk_usuario_token_recuperacion (tokenRecuperacion),

    KEY idx_usuario_tipo (tipoUsuario),
    KEY idx_usuario_estado (estadoUsuario),
    KEY idx_usuario_aerolinea (codAerolinea),

    CONSTRAINT fk_usuario_aerolinea
        FOREIGN KEY (codAerolinea)
        REFERENCES aerolineas(codAerolinea)
        ON UPDATE CASCADE
        ON DELETE RESTRICT

) ENGINE=InnoDB;


-- ============================================================
-- TABLA: VUELOS
-- ============================================================

CREATE TABLE vuelos (
    codVuelo INT UNSIGNED AUTO_INCREMENT,

    codigoVuelo VARCHAR(20) NOT NULL,

    codAerolinea INT UNSIGNED NOT NULL,

    origen VARCHAR(100) NOT NULL,
    destino VARCHAR(100) NOT NULL,

    fecha DATE NOT NULL,
    hora TIME NOT NULL,

    precio DECIMAL(10,2) NOT NULL,

    asientosTotal INT UNSIGNED NOT NULL,
    asientosDisponibles INT UNSIGNED NOT NULL,

    clase ENUM(
        'economica',
        'premium',
        'business'
    ) NOT NULL DEFAULT 'economica',

    -- Baja lógica: 1 = vuelo activo, 0 = eliminado por el CEO
    activo TINYINT(1) NOT NULL DEFAULT 1,

    fechaCreacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fechaActualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (codVuelo),

    UNIQUE KEY uk_vuelo_codigo (codigoVuelo),

    KEY idx_vuelo_aerolinea (codAerolinea),
    KEY idx_vuelo_busqueda (origen, destino, fecha),
    KEY idx_vuelo_fecha (fecha),

    CONSTRAINT fk_vuelo_aerolinea
        FOREIGN KEY (codAerolinea)
        REFERENCES aerolineas(codAerolinea)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT chk_vuelo_origen_destino
        CHECK (origen <> destino),

    CONSTRAINT chk_vuelo_precio
        CHECK (precio > 0),

    CONSTRAINT chk_vuelo_asientos_total
        CHECK (asientosTotal > 0),

    CONSTRAINT chk_vuelo_asientos_disponibles
        CHECK (
            asientosDisponibles >= 0
            AND asientosDisponibles <= asientosTotal
        )

) ENGINE=InnoDB;


-- ============================================================
-- TABLA: PROMOCIONES
-- ============================================================

CREATE TABLE promociones (
    codPromocion INT UNSIGNED AUTO_INCREMENT,

    codAerolinea INT UNSIGNED NOT NULL,

    descripcion VARCHAR(255) NOT NULL,

    descuento DECIMAL(5,2) NOT NULL,

    destino VARCHAR(100) DEFAULT NULL,
    fechaDesde DATE DEFAULT NULL,
    fechaHasta DATE DEFAULT NULL,

    estado ENUM(
        'pendiente',
        'aprobada',
        'denegada'
    ) NOT NULL DEFAULT 'pendiente',

    fechaCreacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fechaActualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (codPromocion),

    KEY idx_promocion_aerolinea (codAerolinea),
    KEY idx_promocion_estado (estado),

    CONSTRAINT fk_promocion_aerolinea
        FOREIGN KEY (codAerolinea)
        REFERENCES aerolineas(codAerolinea)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT chk_promocion_descuento
        CHECK (descuento >= 1 AND descuento <= 100),

    CONSTRAINT chk_promocion_fechas
        CHECK (
            fechaDesde IS NULL
            OR fechaHasta IS NULL
            OR fechaDesde <= fechaHasta
        )

) ENGINE=InnoDB;


-- ============================================================
-- TABLA: NOVEDADES
-- ============================================================

CREATE TABLE novedades (
    codNovedad INT UNSIGNED AUTO_INCREMENT,

    titulo VARCHAR(200) NOT NULL,
    texto TEXT NOT NULL,

    fechaPublicacion DATE NOT NULL,
    fechaExpiracion DATE NOT NULL,

    fechaCreacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fechaActualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (codNovedad),

    KEY idx_novedad_publicacion (fechaPublicacion),
    KEY idx_novedad_expiracion (fechaExpiracion),

    CONSTRAINT chk_novedad_fechas
        CHECK (fechaExpiracion > fechaPublicacion)

) ENGINE=InnoDB;


-- ============================================================
-- TABLA: RESERVAS
-- ============================================================

CREATE TABLE reservas (
    codReserva INT UNSIGNED AUTO_INCREMENT,

    codUsuario INT UNSIGNED NOT NULL,
    codVuelo INT UNSIGNED NOT NULL,

    codPromocion INT UNSIGNED DEFAULT NULL,

    estadoReserva ENUM(
        'pendiente de pago',
        'confirmada',
        'cancelada'
    ) NOT NULL DEFAULT 'pendiente de pago',

    precioFinal DECIMAL(10,2) NOT NULL,

    -- Asiento asignado al pasajero, por ejemplo 4C o 12A.
    -- NULL permite conservar reservas antiguas sin asiento.
    asiento VARCHAR(5) DEFAULT NULL,

    -- Identifica los asientos reservados juntos en una misma compra (hasta 6).
    -- Se confirman y pagan en un único pago. NULL en reservas antiguas:
    -- cada una se paga por separado.
    codCompra VARCHAR(32) DEFAULT NULL,

    fechaReserva DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fechaPago DATETIME DEFAULT NULL,
    fechaCancelacion DATETIME DEFAULT NULL,

    PRIMARY KEY (codReserva),

    KEY idx_reserva_usuario (codUsuario),
    KEY idx_reserva_vuelo (codVuelo),
    KEY idx_reserva_promocion (codPromocion),
    KEY idx_reserva_estado (estadoReserva),
    KEY idx_reserva_fecha (fechaReserva),
    KEY idx_reserva_compra (codCompra),

    CONSTRAINT fk_reserva_usuario
        FOREIGN KEY (codUsuario)
        REFERENCES usuarios(codUsuario)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_reserva_vuelo
        FOREIGN KEY (codVuelo)
        REFERENCES vuelos(codVuelo)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_reserva_promocion
        FOREIGN KEY (codPromocion)
        REFERENCES promociones(codPromocion)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT chk_reserva_precio
        CHECK (precioFinal > 0)

) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;