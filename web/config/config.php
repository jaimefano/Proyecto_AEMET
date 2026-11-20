<?php
// Configuración general. Lee el .env de la raíz del proyecto (el mismo que usa el ETL).

define('RAIZ_PROYECTO', dirname(__DIR__, 2));
define('SESION_INACTIVIDAD', 300); // CU2: la sesión caduca a los 5 minutos sin actividad

// Carpeta de la aplicación en la URL (las páginas están todas en web/)
define('URL_BASE', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/');

function cargar_env(string $ruta): array
{
    $valores = [];
    if (!is_readable($ruta)) {
        return $valores;
    }
    foreach (file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        $linea = trim($linea);
        if ($linea === '' || $linea[0] === '#' || !str_contains($linea, '=')) {
            continue;
        }
        [$clave, $valor] = explode('=', $linea, 2);
        $valores[trim($clave)] = trim($valor, " \t\"'");
    }
    return $valores;
}

$ENV = cargar_env(RAIZ_PROYECTO . '/.env');

define('DB_HOST', $ENV['DB_HOST'] ?? '127.0.0.1');
define('DB_PORT', $ENV['DB_PORT'] ?? '3306');
define('DB_NAME', $ENV['DB_NAME'] ?? 'meteo_aemet');
define('DB_USER', $ENV['DB_USER'] ?? 'root');
define('DB_PASSWORD', $ENV['DB_PASSWORD'] ?? '');
