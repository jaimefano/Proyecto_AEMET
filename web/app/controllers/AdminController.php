<?php
declare(strict_types=1);

// Panel de administración (administracion.php): crear administradores y eliminar usuarios o
// administradores con toda su información. Solo accesible para administradores.
final class AdminController
{
    private const CAMPOS_ADMIN = ['nick', 'nombre', 'apellidos', 'fecha_nacimiento', 'telefono', 'email'];

    public function panel(): void
    {
        Sesion::exigirAdministrador();
        $errores = [];
        $datos = Validador::limpiar([], self::CAMPOS_ADMIN);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Sesion::validarCsrf((string) ($_POST['csrf'] ?? ''))) {
                $errores[] = 'El formulario ha caducado. Inténtalo de nuevo.';
            } elseif (($_POST['accion'] ?? '') === 'eliminar') {
                $this->eliminar((int) ($_POST['id'] ?? 0));   // siempre redirige
            } elseif (($_POST['accion'] ?? '') === 'crear_admin') {
                $datos = Validador::limpiar($_POST, self::CAMPOS_ADMIN);
                $errores = $this->crearAdministrador($datos, (string) ($_POST['password'] ?? ''), (string) ($_POST['password2'] ?? ''));
            }
        }

        $yo = (int) Sesion::usuario()['id'];
        $numAdmins = Usuario::contarAdministradores();
        $filas = [];
        foreach (Usuario::todos() as $u) {
            $id = (int) $u['id_usuario'];
            $esAdmin = $u['rol'] === 'administrador';
            $filas[] = [
                'id'             => $id,
                'nick'           => $u['nick'],
                'nombreCompleto' => $u['nombre'] . ' ' . $u['apellidos'],
                'email'          => $u['email'],
                'rol'            => $u['rol'],
                'edad'           => Usuario::edad($u['fecha_nacimiento']),
                'alta'           => substr((string) $u['fecha_alta'], 0, 10),
                'esYo'           => $id === $yo,
                // No se puede eliminar a uno mismo (para eso está la baja) ni al último administrador.
                'puedeEliminar'  => $id !== $yo && !($esAdmin && $numAdmins <= 1),
            ];
        }

        Vista::render('admin/panel', [
            'errores'  => $errores,
            'valores'  => $datos,
            'usuarios' => $filas,
            'hoy'      => date('Y-m-d'),
        ], 'Administración');
    }

    private function eliminar(int $id): never
    {
        $objetivo = $id > 0 ? Usuario::porId($id) : null;
        if ($objetivo === null) {
            Sesion::ponerAviso('Ese usuario no existe.');
        } elseif ($id === (int) Sesion::usuario()['id']) {
            Sesion::ponerAviso('No puedes eliminar tu propia cuenta desde aquí; usa la opción de darte de baja.');
        } elseif ($objetivo['rol'] === 'administrador' && Usuario::contarAdministradores() <= 1) {
            Sesion::ponerAviso('No se puede eliminar al último administrador.');
        } else {
            Usuario::eliminar($id);
            Sesion::ponerExito('Se ha eliminado a «' . $objetivo['nick'] . '» y toda su información.');
        }
        Vista::redirigir('administracion.php');
    }

    // Devuelve los errores de validación; si no hay ninguno, crea el administrador y redirige.
    private function crearAdministrador(array $datos, string $password, string $repetida): array
    {
        $errores = array_merge(
            Validador::nick($datos['nick']),
            Validador::password($password, $repetida),
            Validador::perfil($datos)
        );
        if (!$errores) {
            if (Usuario::nickExiste($datos['nick'])) {
                $errores[] = 'Ese nombre de usuario ya está en uso.';
            }
            if (Usuario::emailExiste($datos['email'])) {
                $errores[] = 'Ya existe una cuenta con ese email.';
            }
        }
        if ($errores) {
            return $errores;
        }
        try {
            Usuario::crear($datos, $password, 'administrador');
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            return ['Ese nombre de usuario o ese email ya están registrados.'];
        }
        Sesion::ponerExito('Administrador «' . $datos['nick'] . '» creado.');
        Vista::redirigir('administracion.php');
    }
}
