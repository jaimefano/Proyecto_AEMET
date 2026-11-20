// Author: Jaime Fano Alijarde
// Descripcion: Programador del ETL. Se queda en ejecución y lanza la actualización de datos de AEMET
// al arrancar y después cada día a una hora fija (por defecto 00:30, hora de TZ).
//
// Cada ejecución es "node index.js --reanudar": solo descarga desde la última lectura guardada
// (con 7 días de solape). Si la base de datos está vacía, la primera ejecución carga el histórico completo.
//
// Variables opcionales en .env:
//   HORA_ACTUALIZACION=00:30      hora diaria de ejecución (HH:MM)
//   EJECUTAR_AL_ARRANCAR=true     si es "false", espera a la próxima hora programada sin ejecutar al arrancar
// El .env está en la raíz del repositorio (lo comparten la web y el ETL)
require('dotenv').config({ path: require('path').join(__dirname, '..', '.env'), quiet: true });

const { spawn } = require('child_process');
const path = require('path');
const { proximaEjecucion } = require('./src/operacion/programador');
const estado = require('./src/operacion/estado');

const hora = process.env.HORA_ACTUALIZACION || '00:30';
const ejecutarAlArrancar = (process.env.EJECUTAR_AL_ARRANCAR || 'true').toLowerCase() !== 'false';

const ts = () => new Date().toISOString().replace('T', ' ').slice(0, 19);
const log = (msg) => console.log(`[${ts()}] ${msg}`);

// Lanza el ETL como proceso aparte (index.js cierra la conexión y termina al acabar).
function lanzarETL() {
  return new Promise((resolve) => {
    log('Iniciando actualización de datos...');
    const proceso = spawn(process.execPath, [path.join(__dirname, 'index.js'), '--reanudar'], { stdio: 'inherit' });
    proceso.on('close', (codigo) => {
      log(codigo === 0 ? 'Actualización terminada.' : `Actualización terminada con errores (código ${codigo}). Se reintentará en la próxima ejecución.`);
      resolve();
    });
    proceso.on('error', (err) => {
      log(`No se pudo lanzar el ETL: ${err.message}`);
      resolve();
    });
  });
}

const esperar = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

// Permite cerrar el programador con Ctrl+C al momento.
for (const senal of ['SIGTERM', 'SIGINT']) {
  process.on(senal, () => { log('Programador detenido.'); process.exit(0); });
}

(async () => {
  log(`Programador del ETL iniciado. Actualización diaria a las ${hora}.`);
  await estado.preparar();
  await estado.arrancarProgramador(hora);
  // Señal de vida cada minuto: el panel de administración la usa para saber si el ETL sigue activo.
  setInterval(() => estado.latido(null), 60 * 1000).unref();
  if (ejecutarAlArrancar) await lanzarETL();

  while (true) {
    const siguiente = proximaEjecucion(new Date(), hora);
    log(`Próxima actualización: ${siguiente.toLocaleString('es-ES')}`);
    await estado.latido(siguiente);
    await esperar(siguiente - new Date());
    await lanzarETL();
  }
})();
