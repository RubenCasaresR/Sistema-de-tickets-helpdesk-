ALTER TABLE usuarios ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 0;
UPDATE usuarios SET activo = 1 WHERE activo = 0;
