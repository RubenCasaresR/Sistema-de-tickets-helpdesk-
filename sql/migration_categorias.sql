-- Migration: Categorías / Departamentos
-- Ejecutar después del database.sql inicial

CREATE TABLE IF NOT EXISTS categorias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT,
    activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO categorias (nombre, descripcion) VALUES
('Soporte Técnico', 'Problemas con sistemas, software o hardware'),
('Facturación', 'Preguntas y problemas relacionados con pagos y facturas'),
('Recursos Humanos', 'Solicitudes relacionadas con personal y nómina'),
('Infraestructura', 'Redes, servidores y equipamiento'),
('Otros', 'Solicitudes que no encajan en las categorías anteriores');

ALTER TABLE tickets ADD COLUMN categoria_id INT UNSIGNED DEFAULT NULL,
    ADD CONSTRAINT fk_tickets_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL;
