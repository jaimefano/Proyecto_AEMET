<?php
declare(strict_types=1);

// Validación de formularios en el servidor. Cada método devuelve una lista de mensajes de error
// (vacía si todo es correcto). La validación del navegador (atributos HTML) es solo una ayuda:
// esta es la que cuenta.
final class Validador
{
    public const CAMPOS_PERFIL = ['nombre', 'apellidos', 'fecha_nacimiento', 'telefono', 'email'];

    // Devuelve solo los campos indicados, como texto y sin espacios sobrantes.
    // El email se pasa a minúsculas y del teléfono se quitan espacios y guiones.
    public static function limpiar(array $entrada, array $campos): array
    {
        $salida = [];
        foreach ($campos as $campo) {
            $valor = $entrada[$campo] ?? '';
            $salida[$campo] = is_string($valor) ? trim($valor) : '';
        }
        if (isset($salida['email'])) {
            $salida['email'] = mb_strtolower($salida['email']);
        }
        if (isset($salida['telefono'])) {
            $salida['telefono'] = preg_replace('/[\s-]/', '', $salida['telefono']);
        }
        return $salida;
    }

    public static function nick(string $nick): array
    {
        if ($nick === '') {
            return ['El nombre de usuario es obligatorio.'];
        }
        if (mb_strlen($nick) < 3 || mb_strlen($nick) > 30) {
            return ['El nombre de usuario debe tener entre 3 y 30 caracteres.'];
        }
        if (!preg_match('/^[A-Za-z0-9_.-]+$/', $nick)) {
            return ['El nombre de usuario solo puede tener letras, números, punto, guion y guion bajo.'];
        }
        return [];
    }

    // Contraseña nueva y su repetición. Mínimo 6 caracteres (ERS); 72 bytes es el límite de bcrypt.
    public static function password(string $password, string $repetida): array
    {
        $errores = [];
        if (mb_strlen($password) < 6) {
            $errores[] = 'La contraseña debe tener al menos 6 caracteres.';
        } elseif (strlen($password) > 72) {
            $errores[] = 'La contraseña no puede tener más de 72 caracteres.';
        }
        if ($password !== $repetida) {
            $errores[] = 'Las contraseñas no coinciden.';
        }
        return $errores;
    }

    // Datos personales: nombre, apellidos, fecha_nacimiento, telefono y email.
    public static function perfil(array $d): array
    {
        $errores = [];

        $patronNombre = '/^\p{L}[\p{L}\p{M}\s\'.-]*$/u';
        if ($d['nombre'] === '') {
            $errores[] = 'El nombre es obligatorio.';
        } elseif (mb_strlen($d['nombre']) > 100 || !preg_match($patronNombre, $d['nombre'])) {
            $errores[] = 'El nombre no es válido (solo letras, hasta 100 caracteres).';
        }
        if ($d['apellidos'] === '') {
            $errores[] = 'Los apellidos son obligatorios.';
        } elseif (mb_strlen($d['apellidos']) > 150 || !preg_match($patronNombre, $d['apellidos'])) {
            $errores[] = 'Los apellidos no son válidos (solo letras, hasta 150 caracteres).';
        }

        $errores = array_merge($errores, self::fecha($d['fecha_nacimiento']));

        if (!preg_match('/^\+?[0-9]{9,15}$/', $d['telefono'])) {
            $errores[] = 'El teléfono debe tener entre 9 y 15 cifras (puede empezar por +).';
        }
        if ($d['email'] === '' || mb_strlen($d['email']) > 255 || !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El email no es válido.';
        }
        return $errores;
    }

    private static function fecha(string $texto): array
    {
        if ($texto === '') {
            return ['La fecha de nacimiento es obligatoria.'];
        }
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $texto);
        $fallos = DateTimeImmutable::getLastErrors();
        if ($fecha === false || ($fallos && ($fallos['warning_count'] || $fallos['error_count']))
            || $fecha->format('Y-m-d') !== $texto) {
            return ['La fecha de nacimiento no es válida.'];
        }
        if ($fecha > new DateTimeImmutable('today')) {
            return ['La fecha de nacimiento no puede ser futura.'];
        }
        if ($fecha < new DateTimeImmutable('1900-01-01')) {
            return ['La fecha de nacimiento no es válida.'];
        }
        return [];
    }
}
