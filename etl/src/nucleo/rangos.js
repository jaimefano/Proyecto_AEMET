// Author: Jaime Fano Alijarde
// Descripcion: Utilidades de fechas para descargar el histórico por tramos.
// AEMET limita los valores climatológicos diarios a 6 meses por petición.

// Suma días a una fecha 'YYYY-MM-DD' (en UTC, para evitar problemas de zona horaria).
function sumarDias(fecha, dias) {
  const d = new Date(`${fecha}T00:00:00Z`);
  d.setUTCDate(d.getUTCDate() + dias);
  return d.toISOString().slice(0, 10);
}

// Divide [fechaIni, fechaFin] en tramos consecutivos de como máximo maxDias días.
// Por defecto 180 días, que siempre es menos de 6 meses.
function dividirEnTramos(fechaIni, fechaFin, maxDias = 180) {
  const tramos = [];
  let ini = fechaIni;
  while (ini <= fechaFin) {
    let fin = sumarDias(ini, maxDias - 1);
    if (fin > fechaFin) fin = fechaFin;
    tramos.push({ ini, fin });
    ini = sumarDias(fin, 1);
  }
  return tramos;
}

module.exports = { sumarDias, dividirEnTramos };
