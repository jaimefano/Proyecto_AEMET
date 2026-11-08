<?php
declare(strict_types=1);

// Ajustes de la aplicación. Los valores sensibles llegan por variables de entorno
// (las define docker-compose a partir del .env de la raíz del repositorio).
return [
    'app' => [
        'nombre' => 'Climate Change Analyzer',
        // Dirección pública de la web; se usa para construir los enlaces que se envían por email.
        'url'    => rtrim(getenv('APP_URL') ?: 'http://localhost:8000', '/'),
    ],
    'db' => [
        'host'     => getenv('DB_HOST') ?: 'db',
        'port'     => (int) (getenv('DB_PORT') ?: 3306),
        'nombre'   => getenv('DB_NAME') ?: 'meteo_aemet',
        'usuario'  => getenv('DB_USER') ?: 'meteo',
        'password' => getenv('DB_PASSWORD') ?: '',
        'dsn'      => getenv('DB_DSN') ?: '',   // solo para pruebas (p. ej. SQLite); vacío = MySQL
    ],
    // Servidor SMTP para enviar los emails de recuperación de contraseña (variables SMTP_* del .env).
    // seguridad: "tls" (STARTTLS, puerto 587), "ssl" (puerto 465) o "ninguna" (solo pruebas locales).
    'correo' => [
        'host'      => trim((string) getenv('SMTP_HOST')),
        'port'      => (int) (getenv('SMTP_PORT') ?: 587),
        'seguridad' => strtolower(trim((string) (getenv('SMTP_SEGURIDAD') ?: 'tls'))),
        'usuario'   => trim((string) getenv('SMTP_USUARIO')),
        'password'  => str_replace(' ', '', (string) getenv('SMTP_PASSWORD')),   // Google muestra la clave con espacios
        'remitente' => trim((string) (getenv('SMTP_REMITENTE') ?: getenv('SMTP_USUARIO') ?: 'no-reply@cca.local')),
    ],
    'recuperacion' => [
        'validez' => 3600,   // segundos que dura el enlace de recuperación (1 hora)
        'espera'  => 60,     // segundos mínimos entre dos solicitudes del mismo usuario
    ],
    'sesion' => [
        'caducidad' => 300,   // segundos de inactividad antes de cancelar la sesión (5 min, CU2 del ERS)
    ],
];
