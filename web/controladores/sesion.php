<?php
// Gestión de sesión. Caduca a los 5 minutos sin interacción (CU2).

require_once __DIR__ . '/../config/config.php';

function iniciar_sesion(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['ultima_actividad']) && time() - $_SESSION['ultima_actividad'] > SESION_INACTIVIDAD) {
        cerrar_sesion();
        session_start();
    }
    $_SESSION['ultima_actividad'] = time();
}

function cerrar_sesion(): void
{
    $_SESSION = [];
    session_destroy();
}

function hay_sesion(): bool
{
    return isset($_SESSION['id_usuario']);
}

function es_administrador(): bool
{
    return hay_sesion() && ($_SESSION['rol'] ?? '') === 'administrador';
}

// RQ10, RQ18: contenido solo para registrados; si no hay sesión, al login.
function exigir_sesion(): void
{
    if (!hay_sesion()) {
        header('Location: ' . URL_BASE . 'login.php');
        exit;
    }
}

// RQ17, RQ18: solo administradores.
function exigir_administrador(): void
{
    if (!es_administrador()) {
        header('Location: ' . URL_BASE . 'login.php');
        exit;
    }
}

function e(?string $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}
