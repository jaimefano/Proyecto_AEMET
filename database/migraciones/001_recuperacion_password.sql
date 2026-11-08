-- Migración para bases de datos ya creadas (schema.sql solo se ejecuta al crear el volumen).
-- Añade la unicidad del email y la tabla de recuperación de contraseña. Ejecutar UNA vez:
--   docker exec -i meteo_mysql mysql -umeteo -p meteo_aemet < database/migraciones/001_recuperacion_password.sql
-- Si ya existen usuarios con el mismo email, la primera sentencia falla: corrígelos antes.

ALTER TABLE usuarios ADD UNIQUE KEY uq_usuarios_email (email);

CREATE TABLE IF NOT EXISTS recuperaciones_password (
  id_recuperacion INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_usuario      INT UNSIGNED NOT NULL,
  token_hash      CHAR(64)     NOT NULL,
  creada          DATETIME     NOT NULL,
  expira          DATETIME     NOT NULL,
  PRIMARY KEY (id_recuperacion),
  UNIQUE KEY uq_recuperaciones_token (token_hash),
  KEY idx_recuperaciones_usuario (id_usuario),
  CONSTRAINT fk_recuperaciones_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuarios (id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;
