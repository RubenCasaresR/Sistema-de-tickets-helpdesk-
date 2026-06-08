-- Migration: Módulo Gestor de Tareas
-- Fecha: 2026-06-04

-- 1. Tabla de tareas
CREATE TABLE IF NOT EXISTS tareas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT DEFAULT NULL,
    estado ENUM('pendiente','en_progreso','en_revision','completada','cancelada') NOT NULL DEFAULT 'pendiente',
    prioridad ENUM('baja','media','alta','urgente') NOT NULL DEFAULT 'media',
    creador_id INT UNSIGNED NOT NULL,
    asignado_id INT UNSIGNED DEFAULT NULL,
    ticket_id INT UNSIGNED DEFAULT NULL,
    fecha_limite DATE DEFAULT NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_completada DATETIME DEFAULT NULL,
    FOREIGN KEY (creador_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (asignado_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE SET NULL,
    INDEX idx_tareas_estado (estado),
    INDEX idx_tareas_asignado (asignado_id),
    INDEX idx_tareas_prioridad (prioridad),
    INDEX idx_tareas_fecha_limite (fecha_limite)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Subtareas (checklist)
CREATE TABLE IF NOT EXISTS tarea_subtareas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tarea_id INT UNSIGNED NOT NULL,
    texto VARCHAR(255) NOT NULL,
    completado TINYINT(1) NOT NULL DEFAULT 0,
    orden INT UNSIGNED NOT NULL DEFAULT 0,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tarea_id) REFERENCES tareas(id) ON DELETE CASCADE,
    INDEX idx_subtareas_tarea (tarea_id),
    INDEX idx_subtareas_orden (tarea_id, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Comentarios de tareas
CREATE TABLE IF NOT EXISTS tarea_comentarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tarea_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    mensaje TEXT NOT NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tarea_id) REFERENCES tareas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_tcomentarios_tarea (tarea_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Etiquetas
CREATE TABLE IF NOT EXISTS etiquetas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    color VARCHAR(7) NOT NULL DEFAULT '#6366f1',
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Relación tarea ↔ etiqueta
CREATE TABLE IF NOT EXISTS tarea_etiquetas (
    tarea_id INT UNSIGNED NOT NULL,
    etiqueta_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (tarea_id, etiqueta_id),
    FOREIGN KEY (tarea_id) REFERENCES tareas(id) ON DELETE CASCADE,
    FOREIGN KEY (etiqueta_id) REFERENCES etiquetas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Registro de tiempo
CREATE TABLE IF NOT EXISTS tarea_tiempo (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tarea_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME DEFAULT NULL,
    descripcion VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (tarea_id) REFERENCES tareas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_tiempo_tarea (tarea_id),
    INDEX idx_tiempo_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Historial de actividad
CREATE TABLE IF NOT EXISTS tarea_historial (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tarea_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    accion VARCHAR(50) NOT NULL,
    detalle TEXT DEFAULT NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tarea_id) REFERENCES tareas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_historial_tarea (tarea_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Etiquetas por defecto
INSERT IGNORE INTO etiquetas (nombre, color) VALUES
    ('bug', '#dc2626'),
    ('mejora', '#6366f1'),
    ('documentación', '#0891b2'),
    ('urgente', '#ef4444'),
    ('mantenimiento', '#f59e0b'),
    ('desarrollo', '#10b981');
