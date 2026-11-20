// Author: Jaime Fano Alijarde
// Descripcion: Cálculo de la próxima ejecución diaria del ETL a una hora local fija ('HH:MM').

// Devuelve la próxima fecha (Date) en que la hora local sea 'HH:MM', estrictamente posterior a "ahora".
function proximaEjecucion(ahora, hora) {
  const m = /^([01]?\d|2[0-3]):([0-5]\d)$/.exec(String(hora).trim());
  if (!m) throw new Error(`Hora no válida "${hora}": usa el formato HH:MM (por ejemplo 00:30)`);

  const proxima = new Date(ahora);
  proxima.setHours(Number(m[1]), Number(m[2]), 0, 0);
  if (proxima <= ahora) proxima.setDate(proxima.getDate() + 1);
  return proxima;
}

module.exports = { proximaEjecucion };
