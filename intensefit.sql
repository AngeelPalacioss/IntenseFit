-- Base de datos inicial para el proyecto IntenseFit.
-- Ejecutar este archivo desde phpMyAdmin o desde la consola de MariaDB.

CREATE DATABASE IF NOT EXISTS intensefit
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE intensefit;

-- Cuentas que pueden iniciar sesión en el portal.
CREATE TABLE IF NOT EXISTS usuarios (
    id_usuario INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    correo VARCHAR(150) NOT NULL UNIQUE,
    contrasena_hash VARCHAR(255) NOT NULL,
    rol ENUM('cliente', 'admin') NOT NULL DEFAULT 'cliente',
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Datos personales del cliente. La cuenta de acceso está en usuarios.
CREATE TABLE IF NOT EXISTS clientes (
    id_cliente INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT UNSIGNED NOT NULL UNIQUE,
    nombres VARCHAR(80) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    fecha_registro DATE NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_clientes_usuarios
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Planes disponibles para compra y renovación.
CREATE TABLE IF NOT EXISTS planes_membresia (
    id_plan INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(60) NOT NULL UNIQUE,
    duracion_meses TINYINT UNSIGNED NOT NULL,
    precio DECIMAL(10, 2) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- Se conserva una fila por cada membresía o renovación del cliente.
CREATE TABLE IF NOT EXISTS membresias (
    id_membresia INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT UNSIGNED NOT NULL,
    id_plan INT UNSIGNED NOT NULL,
    fecha_inicio DATE NULL,
    fecha_fin DATE NULL,
    estado ENUM('pendiente', 'activa', 'vencida', 'cancelada') NOT NULL DEFAULT 'pendiente',
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_membresias_cliente (id_cliente),
    INDEX idx_membresias_estado (estado),
    CONSTRAINT fk_membresias_clientes
        FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_membresias_planes
        FOREIGN KEY (id_plan) REFERENCES planes_membresia(id_plan)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Un pago pendiente no cuenta como venta. Se cuenta cuando estado = 'pagado'.
CREATE TABLE IF NOT EXISTS pagos (
    id_pago INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_membresia INT UNSIGNED NOT NULL,
    monto DECIMAL(10, 2) NOT NULL,
    moneda CHAR(3) NOT NULL DEFAULT 'MXN',
    estado ENUM('pendiente', 'pagado', 'fallido', 'reembolsado') NOT NULL DEFAULT 'pendiente',
    paypal_order_id VARCHAR(80) NULL UNIQUE,
    paypal_capture_id VARCHAR(80) NULL UNIQUE,
    fecha_pago DATETIME NULL,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pagos_estado (estado),
    CONSTRAINT fk_pagos_membresias
        FOREIGN KEY (id_membresia) REFERENCES membresias(id_membresia)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Precios de ejemplo acordados para el proyecto.
INSERT INTO planes_membresia (nombre, duracion_meses, precio, activo)
VALUES
    ('Plan mensual', 1, 650.00, 1),
    ('Plan trimestral', 3, 1700.00, 1)
ON DUPLICATE KEY UPDATE
    duracion_meses = VALUES(duracion_meses),
    precio = VALUES(precio),
    activo = VALUES(activo);
