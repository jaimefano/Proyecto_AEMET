// Author: Jaime Fano Alijarde
// Descripcion: Orquesta la extracción, transformación e inserción de datos para todas las ciudades.
// El histórico se descarga por tramos (AEMET limita cada petición a 6 meses) y un tramo
// que falla no detiene la carga: se anota y se continúa con el siguiente.
const pool           = require('../operacion/pool');
const { fetchLecturas } = require('../aemet/aemet');
const { parseLectura, parseNum } = require('./parser');
const { dividirEnTramos, sumarDias } = require('./rangos');

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
const SOLAPE_DIAS = 7;   // días que se vuelven a descargar al reanudar

const INSERT_SQL = `
  INSERT INTO lecturas_diarias
    (id_ciudad, fecha, temp_max, temp_min, temp_media,
     precipitacion, precip_inapreciable,
     vel_media_viento, racha_max_viento, horas_sol, pres_max, pres_min,
     dir_viento, hum_media, hum_max, hum_min)
  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
  ON DUPLICATE KEY UPDATE
    temp_max            = VALUES(temp_max),
    temp_min            = VALUES(temp_min),
    temp_media          = VALUES(temp_media),
    precipitacion       = VALUES(precipitacion),
    precip_inapreciable = VALUES(precip_inapreciable),
    vel_media_viento    = VALUES(vel_media_viento),
    racha_max_viento    = VALUES(racha_max_viento),
    horas_sol           = VALUES(horas_sol),
    pres_max            = VALUES(pres_max),
    pres_min            = VALUES(pres_min),
    dir_viento          = VALUES(dir_viento),
    hum_media           = VALUES(hum_media),
    hum_max             = VALUES(hum_max),
    hum_min             = VALUES(hum_min)
`;

// Inserta las lecturas de un tramo en una sola transacción (todo o nada).
async function insertarTramo(idCiudad, raw) {
  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    let n = 0;
    for (const item of raw) {
      if (!item.fecha) continue;
      const l = parseLectura(item);
      await conn.execute(INSERT_SQL, [
        idCiudad, l.fecha, l.temp_max, l.temp_min, l.temp_media,
        l.precipitacion, l.precip_inapreciable,
        l.vel_media_viento, l.racha_max_viento, l.horas_sol,
        l.pres_max, l.pres_min,
        l.dir_viento, l.hum_media, l.hum_max, l.hum_min,
      ]);
      n++;
    }

    // La altitud de la estación viene en cada lectura; se guarda una vez en ciudades.
    const altitud = parseNum(raw.find((r) => r.altitud)?.altitud);
    if (altitud !== null) {
      await conn.execute('UPDATE ciudades SET altitud = ? WHERE id_ciudad = ?', [altitud, idCiudad]);
    }
    await conn.commit();
    return n;
  } catch (err) {
    await conn.rollback();
    throw err;
  } finally {
    conn.release();
  }
}

// Fecha de la última lectura guardada de una ciudad ('YYYY-MM-DD') o null si no hay ninguna.
async function ultimaFecha(idCiudad) {
  const [filas] = await pool.query(
    `SELECT DATE_FORMAT(MAX(fecha), '%Y-%m-%d') AS ultima FROM lecturas_diarias WHERE id_ciudad = ?`,
    [idCiudad]
  );
  return filas[0].ultima;
}

async function procesarCiudad(ciudad, fechaIni, fechaFin, opciones, resumen) {
  const { id_ciudad, indicativo, nombre } = ciudad;

  // Modo reanudar: continúa desde la última lectura guardada, con un solape de unos días
  // porque AEMET publica los últimos días incompletos y se completan más tarde.
  let ini = fechaIni;
  if (opciones.reanudar) {
    const ultima = await ultimaFecha(id_ciudad);
    if (ultima && sumarDias(ultima, -SOLAPE_DIAS) > ini) ini = sumarDias(ultima, -SOLAPE_DIAS);
  }
  if (ini > fechaFin) {
    console.log(`[${nombre}] Nada que descargar.`);
    return;
  }

  const tramos = dividirEnTramos(ini, fechaFin, opciones.tramoDias);
  console.log(`[${nombre}] ${ini} → ${fechaFin} en ${tramos.length} tramo(s).`);

  for (const [i, tramo] of tramos.entries()) {
    const etiqueta = `[${nombre}] tramo ${i + 1}/${tramos.length} (${tramo.ini} → ${tramo.fin})`;
    try {
      const raw = await fetchLecturas(indicativo, tramo.ini, tramo.fin);
      const n = await insertarTramo(id_ciudad, raw);
      resumen.insertadas += n;
      console.log(`${etiqueta}: ${n} lecturas.`);
    } catch (err) {
      if (err.fatal) throw err;
      resumen.fallos.push({ ciudad: nombre, ...tramo, error: err.message });
      console.error(`${etiqueta}: FALLÓ (${err.message})`);
    }
    await sleep(opciones.pausaMs);   // pausa entre tramos para no saturar la API
  }
}

// opciones: { reanudar: bool, tramoDias: number, pausaMs: number }
async function ejecutarETL(fechaIni, fechaFin, opciones = {}) {
  const opts = { reanudar: false, tramoDias: 180, pausaMs: 1500, ...opciones };
  const resumen = { insertadas: 0, fallos: [] };
  const [ciudades] = await pool.query('SELECT id_ciudad, nombre, indicativo FROM ciudades');

  for (const ciudad of ciudades) {
    await procesarCiudad(ciudad, fechaIni, fechaFin, opts, resumen);
  }
  return resumen;
}

module.exports = { ejecutarETL };
