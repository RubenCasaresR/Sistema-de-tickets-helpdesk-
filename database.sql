-- ============================================================
-- Sistema de Tickets Helpdesk
-- Database: MySQL 8.0+
-- ============================================================

CREATE DATABASE IF NOT EXISTS helpdesk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE helpdesk;

-- ============================================================
-- TABLA: usuarios
-- ============================================================
DROP TABLE IF EXISTS comentarios_ticket;
DROP TABLE IF EXISTS tickets;
DROP TABLE IF EXISTS usuarios;

CREATE TABLE usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol ENUM('cliente', 'soporte', 'admin') NOT NULL DEFAULT 'cliente',
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA: tickets
-- ============================================================
CREATE TABLE tickets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    folio VARCHAR(10) NOT NULL UNIQUE,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT NOT NULL,
    estado ENUM('abierto', 'en_progreso', 'resuelto', 'cerrado') NOT NULL DEFAULT 'abierto',
    prioridad ENUM('baja', 'media', 'alta', 'urgente') NOT NULL DEFAULT 'media',
    creador_id INT UNSIGNED NOT NULL,
    asignado_id INT UNSIGNED DEFAULT NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_cierre DATETIME DEFAULT NULL,
    CONSTRAINT fk_tickets_creador FOREIGN KEY (creador_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_tickets_asignado FOREIGN KEY (asignado_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_tickets_estado ON tickets(estado);
CREATE INDEX idx_tickets_prioridad ON tickets(prioridad);
CREATE INDEX idx_tickets_creador ON tickets(creador_id);
CREATE INDEX idx_tickets_asignado ON tickets(asignado_id);

-- ============================================================
-- TABLA: comentarios_ticket
-- ============================================================
CREATE TABLE comentarios_ticket (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    mensaje TEXT NOT NULL,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_comentarios_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_comentarios_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_comentarios_ticket ON comentarios_ticket(ticket_id);

-- ============================================================
-- SEEDERS — Datos de prueba
-- Passwords: 'password123' (hash generado con password_hash)
-- ============================================================

INSERT INTO usuarios (nombre, email, password, rol) VALUES
('Admin Principal', 'admin@helpdesk.com', '$2y$12$macVqzDmUY7G25xkXsZ5c.ldVoR0e6crydpOInAPh49v5zd5XOHQm', 'admin'),
('Soporte Técnico', 'soporte@helpdesk.com', '$2y$12$macVqzDmUY7G25xkXsZ5c.ldVoR0e6crydpOInAPh49v5zd5XOHQm', 'soporte'),
('Cliente Demo', 'cliente@helpdesk.com', '$2y$12$macVqzDmUY7G25xkXsZ5c.ldVoR0e6crydpOInAPh49v5zd5XOHQm', 'cliente');

INSERT INTO tickets (folio, titulo, descripcion, estado, prioridad, creador_id, asignado_id, fecha_creacion) VALUES
('TCK-00001', 'No puedo iniciar sesión en el sistema', 'Hola, desde esta mañana no logro acceder a mi cuenta. He intentado restablecer la contraseña pero no recibo el correo.', 'en_progreso', 'alta', 3, 2, '2026-05-30 09:15:00'),
('TCK-00002', 'Error en el módulo de reportes', 'Al generar un reporte mensual, el sistema muestra un error 500 en la línea de totales.', 'abierto', 'urgente', 3, NULL, '2026-06-01 08:30:00'),
('TCK-00003', 'Solicitud de nuevo usuario', 'Necesito crear una cuenta para el nuevo empleado del departamento de ventas.', 'resuelto', 'baja', 3, 2, '2026-05-28 14:00:00'),
('TCK-00004', 'Actualización de datos fiscales', 'Requiero actualizar el RFC y razón social de mi perfil para facturación.', 'abierto', 'media', 3, 2, '2026-06-01 07:45:00');

INSERT INTO comentarios_ticket (ticket_id, usuario_id, mensaje, fecha) VALUES
(1, 2, 'Hola, hemos revisado tu cuenta y parece que el correo de recuperación está bloqueado. ¿Podrías confirmar tu dirección de correo alternativa?', '2026-05-30 10:00:00'),
(1, 3, 'Mi correo alternativo es cliente.demo@alternativo.com. Gracias por la atención.', '2026-05-30 10:30:00'),
(1, 2, 'Perfecto, hemos actualizado tu correo alternativo. Ya deberías poder restablecer tu contraseña. Quedamos atentos.', '2026-05-30 11:00:00'),
(3, 2, 'La cuenta ha sido creada exitosamente. Los datos de acceso se enviaron al correo del gerente de ventas.', '2026-05-28 15:00:00');
