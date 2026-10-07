-- Base de datos del ejemplo "Partido Despedida de Messi"
-- Importar completa antes de correr los PHP

CREATE DATABASE IF NOT EXISTS entradas_messi CHARACTER SET utf8mb4;
USE entradas_messi;

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(32) NOT NULL
);

INSERT INTO usuarios (usuario, password) VALUES
('facu', MD5('1234')),
('hincha', MD5('boca123'));

CREATE TABLE evento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    fecha_evento DATETIME NOT NULL,
    estadio VARCHAR(150) NOT NULL
);

INSERT INTO evento (nombre, descripcion, fecha_evento, estadio) VALUES
('Un Partido Para la Historia', 'Partido homenaje de despedida de Lionel Messi de la Selección Argentina, con invitados especiales de la Scaloneta campeona del mundo.', '2026-12-20 20:00:00', 'Estadio Más Monumental, River Plate');

CREATE TABLE lugares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sector VARCHAR(50) NOT NULL,
    precio DECIMAL(10,2) NOT NULL,
    disponible TINYINT(1) NOT NULL DEFAULT 1
);

INSERT INTO lugares (sector, precio, disponible) VALUES
('Popular', 80000.00, 1),
('Popular', 80000.00, 1),
('Popular', 80000.00, 1),
('Popular', 80000.00, 1),
('Platea', 150000.00, 1),
('Platea', 150000.00, 1),
('Platea', 150000.00, 1),
('Palco', 300000.00, 1),
('Palco', 300000.00, 1),
('VIP', 500000.00, 1);

CREATE TABLE reservas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lugar_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    dni VARCHAR(20) NOT NULL,
    email VARCHAR(150) NOT NULL,
    telefono VARCHAR(30) NOT NULL,
    fecha_reserva DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lugar_id) REFERENCES lugares(id)
);
