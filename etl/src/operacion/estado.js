// Descripcion: Estado del ETL en la tabla etl_estado (una sola fila). Lo escribe el ETL y lo lee el panel
// de administración de la web. Las fechas se guardan en UTC. Un fallo al escribir el estado nunca debe
// interrumpir la descarga de datos: se registra en el log y se continúa.
const pool = require('./pool');

const CREAR_TABLA = `
  CREATE TABLE IF NOT EXISTS etl_estado (
    id_estado          TINYINT UNSIGNED NOT NULL,
    ejecutando         BOOLEAN          NOT NULL DEFAULT FALSE,
    inicio_ultima      DATETIME         NULL,
    fin_ultima         DATETIME         NULL,
    resultado_ultima   ENUM('correcta','con_errores','fallida') NULL,
    lecturas_ultima    INT UNSIGNED     NOT NULL DEFAULT 0,
    fallos_ultima      INT UNSIGNED     NOT NULL DEFAULT 0,
    mensaje_ultima     VARCHAR(500)     NULL,
    programador_latido DATETIME         NULL,
    proxima_ejecucion  DATETIME         NULL,
    hora_programada    VARCHAR(5)       NULL,
    PRIMARY KEY (id_estado)
  ) ENGINE=InnoDB`;

// Convierte un Date a 'YYYY-MM-DD HH:MM:SS' en UTC (o null).
const utc = (d) => (d ? d.toISOString().slice(0, 19).replace('T', ' ') : null);

async function seguro(accion) {
  try {
    await accion();
  } catch (err) {
    console.error(`(No se pudo guardar el estado del ETL: ${err.message})`);
  }
}

// Crea la tabla y la fila única si no existen.
const preparar = () => seguro(async () => {
  await pool.query(CREAR_TABLA);
  await pool.query('INSERT IGNORE INTO etl_estado (id_estado) VALUES (1)');
});

// El programador arranca: una ejecución que quedara "en curso" por un cierre brusco ya no lo está.
const arrancarProgramador = (hora) => seguro(() => pool.query(
  'UPDATE etl_estado SET ejecutando = FALSE, hora_programada = ?, programador_latido = UTC_TIMESTAMP() WHERE id_estado = 1', [hora]));

// Señal de vida del programador (cada minuto) y próxima ejecución prevista.
const latido = (proxima) => seguro(() => pool.query(
  'UPDATE etl_estado SET programador_latido = UTC_TIMESTAMP(), proxima_ejecucion = COALESCE(?, proxima_ejecucion) WHERE id_estado = 1',
  [utc(proxima)]));

const inicioEjecucion = () => seguro(() => pool.query(
  'UPDATE etl_estado SET ejecutando = TRUE, inicio_ultima = UTC_TIMESTAMP() WHERE id_estado = 1'));

// resultado: 'correcta' | 'con_errores' | 'fallida'
const finEjecucion = (resultado, lecturas, fallos, mensaje) => seguro(() => pool.query(
  `UPDATE etl_estado SET ejecutando = FALSE, fin_ultima = UTC_TIMESTAMP(), resultado_ultima = ?,
     lecturas_ultima = ?, fallos_ultima = ?, mensaje_ultima = ? WHERE id_estado = 1`,
  [resultado, lecturas, fallos, mensaje ? String(mensaje).slice(0, 500) : null]));

module.exports = { preparar, arrancarProgramador, latido, inicioEjecucion, finEjecucion };
