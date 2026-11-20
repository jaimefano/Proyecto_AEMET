<?php
// Controlador de la página de inicio.

require_once __DIR__ . '/sesion.php';

iniciar_sesion();

$titulo = 'Inicio';
$sesion_activa = hay_sesion();

require __DIR__ . '/../vistas/inicio.php';
