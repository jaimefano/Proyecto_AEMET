<?php
declare(strict_types=1);

// Gestión de la sesión de usuario, incluida la caducidad por inactividad (CU2 del ERS):
// si un usuario identificado no interactúa durante 5 minutos, su sesión se cancela.
final class Sesion
{
    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
        session_start();

        if (self::haCaducado()) {
            self::cerrar();
            $_SESSION['aviso'] = 'Tu sesión ha caducado por inactividad. Vuelve a identificarte.';
        }
        if (self::estaIdentificado()) {
            $_SESSION['ultima_actividad'] = time();   // cada petición reinicia el contador
        }
    }

    // True si hay un usuario identificado y han pasado más segundos de los permitidos desde su última actividad.
    public static function haCaducado(): bool
    {
        if (!self::estaIdentificado()) {
            return false;
        }
        $ultima = (int) ($_SESSION['ultima_actividad'] ?? 0);
        return (time() - $ultima) > (int) Config::get('sesion.caducidad', 300);
    }

    // Registra al usuario en la sesión (se llamará desde el login, una vez validada la contraseña).
    public static function identificar(array $usuario): void
    {
        session_regenerate_id(true);   // evita la fijación de sesión
        $_SESSION['usuario'] = [
            'id'   => (int) $usuario['id_usuario'],
            'nick' => (string) $usuario['nick'],
            'rol'  => (string) $usuario['rol'],
        ];
        $_SESSION['ultima_actividad'] = time();
    }

    // Cierra la sesión del usuario. Deja una sesión nueva y vacía (con otro identificador), de modo que
    // se puede mostrar un mensaje en la página siguiente ("has cerrado la sesión", "sesión caducada"...).
    public static function cerrar(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);   // borra la sesión anterior y envía al navegador la cookie nueva
    }

    public static function estaIdentificado(): bool
    {
        return isset($_SESSION['usuario']);
    }

    public static function usuario(): ?array
    {
        return $_SESSION['usuario'] ?? null;
    }

    public static function esAdministrador(): bool
    {
        return (self::usuario()['rol'] ?? '') === 'administrador';
    }

    // Devuelve y borra el aviso pendiente (p. ej. "sesión caducada") para mostrarlo una sola vez.
    public static function tomarAviso(): ?string
    {
        $aviso = $_SESSION['aviso'] ?? null;
        unset($_SESSION['aviso']);
        return $aviso;
    }

    // ---- Mensajes de una sola vez (se muestran en la siguiente página y se borran) ----

    public static function ponerAviso(string $texto): void
    {
        $_SESSION['aviso'] = $texto;
    }

    public static function ponerExito(string $texto): void
    {
        $_SESSION['exito'] = $texto;
    }

    public static function tomarExito(): ?string
    {
        $exito = $_SESSION['exito'] ?? null;
        unset($_SESSION['exito']);
        return $exito;
    }

    // ---- Protección de páginas ----

    // Páginas privadas: si no hay sesión, se envía al usuario a identificarse.
    public static function exigirIdentificado(): void
    {
        if (!self::estaIdentificado()) {
            $_SESSION['aviso'] ??= 'Debes iniciar sesión para acceder a esa página.';
            Vista::redirigir('login.php');
        }
    }

    public static function exigirAdministrador(): void
    {
        self::exigirIdentificado();
        if (!self::esAdministrador()) {
            self::ponerAviso('No tienes permiso para acceder a la administración.');
            Vista::redirigir('index.php');
        }
    }

    // ---- Protección CSRF de los formularios (cada formulario POST lleva este token) ----

    public static function tokenCsrf(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function validarCsrf(string $token): bool
    {
        return $token !== '' && hash_equals(self::tokenCsrf(), $token);
    }
}
