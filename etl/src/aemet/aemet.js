// Author: Jaime Fano Alijarde
// Descripcion: Cliente HTTP para la API REST de AEMET OpenData (valores climatológicos diarios).
// Reintenta automáticamente ante límite de peticiones (429), errores 5xx y fallos de red.
const BASE_URL = 'https://opendata.aemet.es/opendata';

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

// Parámetros de reintento (configurables desde .env)
function configReintentos() {
  return {
    maxIntentos: Number(process.env.MAX_INTENTOS) || 5,
    espera429:   Number(process.env.ESPERA_429_MS) || 60000,   // AEMET limita las peticiones por minuto
    esperaError: Number(process.env.ESPERA_ERROR_MS) || 5000,  // se multiplica por el nº de intento
  };
}

// Hace un GET con reintentos. Devuelve la respuesta cuando no es 429 ni 5xx.
// Lanza un error "fatal" si la API key no es válida (401/403): no tiene sentido reintentar.
async function pedir(url) {
  const { maxIntentos, espera429, esperaError } = configReintentos();
  let ultimoError;

  for (let intento = 1; intento <= maxIntentos; intento++) {
    let res;
    try {
      res = await fetch(url);
    } catch (err) {
      ultimoError = err;
      await sleep(esperaError * intento);
      continue;
    }

    if (res.status === 401 || res.status === 403) {
      const err = new Error(`AEMET rechazó la API key (HTTP ${res.status})`);
      err.fatal = true;
      throw err;
    }
    if (res.status === 429) {
      ultimoError = new Error('límite de peticiones de AEMET (429)');
      await sleep(espera429);
      continue;
    }
    if (res.status >= 500) {
      ultimoError = new Error(`AEMET error ${res.status}`);
      await sleep(esperaError * intento);
      continue;
    }
    return res;
  }
  throw new Error(`Sin respuesta de AEMET tras ${maxIntentos} intentos: ${ultimoError?.message}`);
}

// Obtiene las lecturas diarias de una estación entre dos fechas (YYYY-MM-DD).
// El rango no puede superar 6 meses (usar rangos.dividirEnTramos para periodos largos).
// AEMET requiere dos peticiones: la primera devuelve una URL temporal con los datos.
// Devuelve [] si AEMET responde que no hay datos para ese rango.
async function fetchLecturas(indicativo, fechaIni, fechaFin) {
  const apiKey = process.env.AEMET_API_KEY;
  if (!apiKey) {
    const err = new Error('AEMET_API_KEY no definida en .env');
    err.fatal = true;
    throw err;
  }

  const endpoint =
    `${BASE_URL}/api/valores/climatologicos/diarios/datos` +
    `/fechaini/${fechaIni}T00:00:00UTC` +
    `/fechafin/${fechaFin}T23:59:59UTC` +
    `/estacion/${indicativo}` +
    `?api_key=${apiKey}`;

  // Primera petición: obtiene la URL de datos
  const res1 = await pedir(endpoint);
  if (res1.status === 404) return [];
  const meta = await res1.json().catch(() => null);
  if (!res1.ok || !meta) throw new Error(`AEMET error ${res1.status} para estación ${indicativo}`);
  if (meta.estado === 404) return [];
  if (meta.estado !== 200) {
    throw new Error(`AEMET respuesta inesperada (estado ${meta.estado}): ${meta.descripcion}`);
  }

  // Segunda petición: obtiene el array de lecturas
  const res2 = await pedir(meta.datos);
  if (res2.status === 404) return [];
  if (!res2.ok) throw new Error(`Error ${res2.status} al obtener datos de ${meta.datos}`);
  return res2.json();
}

module.exports = { fetchLecturas };
