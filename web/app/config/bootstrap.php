<?php
declare(strict_types=1);

// Punto de arranque común: lo incluye cada página de public/ antes de llamar a su controlador.
define('RAIZ_APP', dirname(__DIR__));
date_default_timezone_set('Europe/Madrid');

// Carga automática de clases desde las carpetas de la aplicación.
spl_autoload_register(static function (string $clase): void {
    foreach (['config', 'helpers', 'models', 'controllers'] as $carpeta) {
        $fichero = RAIZ_APP . "/$carpeta/$clase.php";
        if (is_file($fichero)) {
            require_once $fichero;
            return;
        }
    }
});

Sesion::iniciar();
