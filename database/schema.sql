-- =====================================================================
-- Práctica de investigación: datos meteorológicos AEMET -> MySQL
-- Author: Jaime Fano Alijarde
-- Esquema (fase 1): campos mínimos del enunciado + "otros datos".
-- NOTA: las columnas extra corresponden a campos del endpoint de valores
-- climatológicos diarios de AEMET (tmed, velmedia, racha, dir, sol, presMax,
-- presMin, hrMedia, hrMax, hrMin), verificados contra respuestas reales.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS meteo_aemet
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE meteo_aemet;

-- ---------------------------------------------------------------------
-- Tabla ciudades: una fila por ciudad, vinculada a su estación AEMET.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ciudades (
  id_ciudad   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre      VARCHAR(100) NOT NULL,   -- p. ej. "Zaragoza"
  indicativo  VARCHAR(10)  NOT NULL,   -- idema de AEMET (puede llevar letras: "0201D")
  altitud     SMALLINT     NULL,       -- m sobre el nivel del mar (altitud de la estación, la rellena el ETL)
  PRIMARY KEY (id_ciudad),
  UNIQUE KEY uq_ciudades_indicativo (indicativo)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabla lecturas_diarias: un registro por estación y día.
-- Las medidas admiten NULL porque AEMET no siempre informa todos los datos.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS lecturas_diarias (
  id_lectura          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_ciudad           INT UNSIGNED    NOT NULL,
  fecha               DATE            NOT NULL,
  -- Campos del enunciado
  temp_max            DECIMAL(4,1)    NULL,   -- °C (tmax)
  temp_min            DECIMAL(4,1)    NULL,   -- °C (tmin)
  precipitacion       DECIMAL(5,1)    NULL,   -- mm (prec)
  -- Otros datos
  temp_media          DECIMAL(4,1)    NULL,   -- °C (tmed)
  precip_inapreciable BOOLEAN         NOT NULL DEFAULT FALSE, -- AEMET devuelve "Ip" (<0,1 mm)
  vel_media_viento    DECIMAL(4,1)    NULL,   -- m/s (velmedia)
  racha_max_viento    DECIMAL(4,1)    NULL,   -- m/s (racha)
  horas_sol           DECIMAL(4,1)    NULL,   -- horas (sol)
  pres_max            DECIMAL(5,1)    NULL,   -- hPa (presMax)
  pres_min            DECIMAL(5,1)    NULL,   -- hPa (presMin)
  dir_viento          TINYINT UNSIGNED NULL,  -- decenas de grado, 0-36 (dir); 99 = dirección variable
  hum_media           TINYINT UNSIGNED NULL,  -- % humedad relativa media (hrMedia)
  hum_max             TINYINT UNSIGNED NULL,  -- % humedad relativa máxima (hrMax)
  hum_min             TINYINT UNSIGNED NULL,  -- % humedad relativa mínima (hrMin)
  PRIMARY KEY (id_lectura),
  -- Una sola lectura por ciudad y día: permite relanzar el script sin duplicar
  UNIQUE KEY uq_lectura_ciudad_fecha (id_ciudad, fecha),
  KEY idx_lecturas_fecha (fecha),
  CONSTRAINT fk_lecturas_ciudad
    FOREIGN KEY (id_ciudad) REFERENCES ciudades (id_ciudad)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Datos iniciales. COMPROBAR los indicativos en datosclima.es o con el
-- inventario de estaciones de AEMET antes de usarlos.
-- ---------------------------------------------------------------------
INSERT INTO ciudades (nombre, indicativo) VALUES
  ('Madrid',    '3195'),
  ('Barcelona', '0201D'),
  ('Valencia',  '8416'),
  ('Zaragoza',  '9434'),
  ('Teruel',    '8368U')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- =====================================================================
-- Proyecto Climate Change Analyzer (aplicación web)
-- Tabla de usuarios. Los tipos de usuario del ERS son: usuario (sin
-- registrar, no se guarda), usuario registrado y administrador (columna rol).
-- Las contraseñas se guardan con hash (password_hash de PHP), nunca en claro.
-- La edad (RQ15) se calcula a partir de fecha_nacimiento.
-- =====================================================================
CREATE TABLE IF NOT EXISTS usuarios (
  id_usuario       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  nick             VARCHAR(50)   NOT NULL,   -- nombre de usuario, único en el sistema
  password_hash    VARCHAR(255)  NOT NULL,   -- hash bcrypt generado con password_hash()
  nombre           VARCHAR(100)  NOT NULL,
  apellidos        VARCHAR(150)  NOT NULL,
  fecha_nacimiento DATE          NOT NULL,
  telefono         VARCHAR(20)   NOT NULL,
  email            VARCHAR(255)  NOT NULL,
  rol              ENUM('registrado','administrador') NOT NULL DEFAULT 'registrado',
  fecha_alta       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_usuario),
  UNIQUE KEY uq_usuarios_nick (nick),
  UNIQUE KEY uq_usuarios_email (email)   -- el email identifica al usuario en la recuperación de contraseña
) ENGINE=InnoDB;

-- Solicitudes de recuperación de contraseña. Se guarda solo el hash (SHA-256) del token que se envía
-- por email, de modo que una filtración de la base de datos no permite restablecer contraseñas.
-- Cada solicitud caduca (expira) y se borra al usarse; al eliminar al usuario se borran con él.
-- Las fechas se guardan en UTC.
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

-- Administrador por defecto (RQ11).
-- El hash corresponde a la contraseña "pepe" (bcrypt, generado con password_hash).
INSERT INTO usuarios (nick, password_hash, nombre, apellidos, fecha_nacimiento, telefono, email, rol)
VALUES ('pepe', '$2y$10$E19.dn/mBuh33kqVWvnoGer8f8FnaMi.4ZWC0voLQcYPBQxv.8Eye',
        'Pepe', 'Administrador', '2000-01-01', '000000000', 'admin@cca.local', 'administrador')
ON DUPLICATE KEY UPDATE nick = nick;
