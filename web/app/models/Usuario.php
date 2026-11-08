<?php
declare(strict_types=1);

// Acceso a la tabla usuarios. Todas las consultas son preparadas (sin concatenar datos del usuario).
// Las contraseñas nunca se guardan en claro: se almacena el hash que genera password_hash().
final class Usuario
{
    public static function porId(int $id): ?array
    {
        return self::uno('SELECT * FROM usuarios WHERE id_usuario = ?', [$id]);
    }

    public static function porNick(string $nick): ?array
    {
        return self::uno('SELECT * FROM usuarios WHERE nick = ?', [$nick]);
    }

    public static function porEmail(string $email): ?array
    {
        return self::uno('SELECT * FROM usuarios WHERE email = ?', [mb_strtolower($email)]);
    }

    public static function nickExiste(string $nick): bool
    {
        return self::porNick($nick) !== null;
    }

    // $excepto permite ignorar al propio usuario cuando modifica su perfil.
    public static function emailExiste(string $email, int $excepto = 0): bool
    {
        $u = self::porEmail($email);
        return $u !== null && (int) $u['id_usuario'] !== $excepto;
    }

    // $datos: nick, nombre, apellidos, fecha_nacimiento, telefono, email. Devuelve el id creado.
    public static function crear(array $datos, string $password, string $rol = 'registrado'): int
    {
        $pdo = Database::conexion();
        $pdo->prepare(
            'INSERT INTO usuarios (nick, password_hash, nombre, apellidos, fecha_nacimiento, telefono, email, rol)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $datos['nick'],
            password_hash($password, PASSWORD_DEFAULT),
            $datos['nombre'],
            $datos['apellidos'],
            $datos['fecha_nacimiento'],
            $datos['telefono'],
            mb_strtolower($datos['email']),
            $rol,
        ]);
        return (int) $pdo->lastInsertId();
    }

    // El nick no se puede cambiar: identifica al usuario en todo el sistema.
    public static function actualizarPerfil(int $id, array $datos): void
    {
        Database::conexion()->prepare(
            'UPDATE usuarios SET nombre = ?, apellidos = ?, fecha_nacimiento = ?, telefono = ?, email = ?
             WHERE id_usuario = ?'
        )->execute([
            $datos['nombre'],
            $datos['apellidos'],
            $datos['fecha_nacimiento'],
            $datos['telefono'],
            mb_strtolower($datos['email']),
            $id,
        ]);
    }

    public static function cambiarPassword(int $id, string $password): void
    {
        Database::conexion()
            ->prepare('UPDATE usuarios SET password_hash = ? WHERE id_usuario = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    }

    // Elimina al usuario con toda su información (sus solicitudes de recuperación también).
    public static function eliminar(int $id): void
    {
        $pdo = Database::conexion();
        $pdo->prepare('DELETE FROM recuperaciones_password WHERE id_usuario = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM usuarios WHERE id_usuario = ?')->execute([$id]);
    }

    public static function todos(): array
    {
        return Database::conexion()
            ->query('SELECT id_usuario, nick, nombre, apellidos, fecha_nacimiento, email, rol, fecha_alta
                     FROM usuarios ORDER BY nick')
            ->fetchAll();
    }

    public static function contarAdministradores(): int
    {
        return (int) Database::conexion()
            ->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'administrador'")
            ->fetchColumn();
    }

    // La edad (RQ15) se calcula a partir de la fecha de nacimiento.
    public static function edad(string $fechaNacimiento): int
    {
        return (new DateTimeImmutable($fechaNacimiento))->diff(new DateTimeImmutable('today'))->y;
    }

    private static function uno(string $sql, array $parametros): ?array
    {
        $st = Database::conexion()->prepare($sql);
        $st->execute($parametros);
        $fila = $st->fetch();
        return $fila === false ? null : $fila;
    }
}
