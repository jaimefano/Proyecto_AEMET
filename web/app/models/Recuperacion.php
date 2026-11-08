<?php
declare(strict_types=1);

// Solicitudes de recuperación de contraseña (tabla recuperaciones_password).
// De cada token solo se guarda su hash SHA-256; el token en claro viaja únicamente en el email.
// Las fechas se guardan en UTC y se comparan en PHP, para no depender de la zona horaria de MySQL.
final class Recuperacion
{
    private const FORMATO = 'Y-m-d H:i:s';

    // Crea una solicitud nueva y anula las anteriores del mismo usuario.
    public static function crear(int $idUsuario, string $tokenHash, int $validez): void
    {
        self::eliminarDeUsuario($idUsuario);
        Database::conexion()->prepare(
            'INSERT INTO recuperaciones_password (id_usuario, token_hash, creada, expira) VALUES (?, ?, ?, ?)'
        )->execute([
            $idUsuario,
            $tokenHash,
            gmdate(self::FORMATO),
            gmdate(self::FORMATO, time() + $validez),
        ]);
    }

    // Momento (timestamp) de la última solicitud del usuario, o null si no tiene ninguna.
    public static function ultimaCreada(int $idUsuario): ?int
    {
        $st = Database::conexion()->prepare(
            'SELECT creada FROM recuperaciones_password WHERE id_usuario = ? ORDER BY creada DESC LIMIT 1'
        );
        $st->execute([$idUsuario]);
        $creada = $st->fetchColumn();
        return $creada === false ? null : (int) strtotime($creada . ' UTC');
    }

    // Devuelve la solicitud si el token existe y no ha caducado; si ha caducado, la borra.
    public static function buscarValida(string $tokenHash): ?array
    {
        $st = Database::conexion()->prepare('SELECT * FROM recuperaciones_password WHERE token_hash = ?');
        $st->execute([$tokenHash]);
        $fila = $st->fetch();
        if ($fila === false) {
            return null;
        }
        if (strtotime($fila['expira'] . ' UTC') < time()) {
            self::eliminarDeUsuario((int) $fila['id_usuario']);
            return null;
        }
        return $fila;
    }

    public static function eliminarDeUsuario(int $idUsuario): void
    {
        Database::conexion()
            ->prepare('DELETE FROM recuperaciones_password WHERE id_usuario = ?')
            ->execute([$idUsuario]);
    }
}
