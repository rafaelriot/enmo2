-- Migración 002: Agregar foto_url a la tabla usuarios
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS foto_url VARCHAR(500) NULL AFTER email;
