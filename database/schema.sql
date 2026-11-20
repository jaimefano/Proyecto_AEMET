-- =====================================================================
-- Práctica de investigación: datos meteorológicos AEMET -> MySQL
-- Author: Jaime Fano Alijarde
-- Esquema (fase 1): campos mínimos del enunciado + "otros datos".
-- NOTA: las columnas extra corresponden a campos del endpoint de valores
-- climatológicos diarios de AEMET (tmed, velmedia, racha, dir, sol, presMax,
-- presMin, hrMedia, hrMax, hrMin), verificados contra respuestas reales.
-- =====================================================================

-- Cada importación borra la base de datos y la crea de nuevo desde cero
-- (se pierden también las lecturas descargadas por el ETL).
DROP DATABASE IF EXISTS meteo_aemet;

CREATE DATABASE meteo_aemet
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
-- Las contraseñas se guardan con hash (password_hash de PHP), nunca en claro (RQ27).
-- Datos de usuario: unión de CU1 (nombre, apellidos, fecha de nacimiento, e-mail,
-- contraseña) y RQ15 (nick, contraseña, nombre, apellidos, edad, teléfono, e-mail).
-- La edad se calcula a partir de fecha_nacimiento. (Duda abierta del ERS, sin resolver.)
-- RQ23: todas las tablas tienen un campo ID (clave primaria autonumérica).
-- RQ13/CAR07: si se añaden tablas con datos de un usuario, deben llevar
-- FOREIGN KEY ... REFERENCES usuarios (id_usuario) ON DELETE CASCADE.
-- La longitud mínima de la contraseña (CAR03) y el resto de comprobaciones de
-- formulario (RQ9, RQ22) se validan en PHP, no en la base de datos.
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
  UNIQUE KEY uq_usuarios_nick (nick)   -- RQ14, RQ16, CAR02: el nick es único en todo el sistema
) ENGINE=InnoDB;

-- Administrador por defecto (RQ11): usuario "pepe", contraseña "pepe".
-- El hash es bcrypt (password_hash) y está verificado con password_verify.
INSERT INTO usuarios (nick, password_hash, nombre, apellidos, fecha_nacimiento, telefono, email, rol)
VALUES ('pepe', '$2y$10$E19.dn/mBuh33kqVWvnoGer8f8FnaMi.4ZWC0voLQcYPBQxv.8Eye',
        'Pepe', 'Administrador', '2000-01-01', '000000000', 'admin@cca.local', 'administrador')
ON DUPLICATE KEY UPDATE nick = nick;

-- Estado del ETL (lo escribe el ETL, lo lee el panel de administración). Una sola fila (id_estado = 1).
-- Las fechas se guardan en UTC. El programador del ETL actualiza programador_latido cada minuto:
-- si no se renueva, el panel considera que el ETL está parado.
CREATE TABLE IF NOT EXISTS etl_estado (
  id_estado          TINYINT UNSIGNED NOT NULL,
  ejecutando         BOOLEAN          NOT NULL DEFAULT FALSE,
  inicio_ultima      DATETIME         NULL,       -- inicio de la última ejecución
  fin_ultima         DATETIME         NULL,       -- fin de la última ejecución
  resultado_ultima   ENUM('correcta','con_errores','fallida') NULL,
  lecturas_ultima    INT UNSIGNED     NOT NULL DEFAULT 0,   -- lecturas insertadas/actualizadas
  fallos_ultima      INT UNSIGNED     NOT NULL DEFAULT 0,   -- tramos que fallaron
  mensaje_ultima     VARCHAR(500)     NULL,       -- primer error, si lo hubo
  programador_latido DATETIME         NULL,       -- última señal de vida del programador
  proxima_ejecucion  DATETIME         NULL,
  hora_programada    VARCHAR(5)       NULL,       -- HH:MM diaria (hora local del ETL)
  PRIMARY KEY (id_estado)
) ENGINE=InnoDB;
