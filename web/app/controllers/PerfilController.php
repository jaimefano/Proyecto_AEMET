<?php
declare(strict_types=1);

// Perfil del usuario identificado: consultar y modificar datos, cambiar la contraseña y darse de baja.
final class PerfilController
{
    public function ver(): void
    {
        Sesion::exigirIdentificado();
        $usuario = $this->usuarioActual();
        $errores = [];
        $datos = Validador::limpiar($usuario, Validador::CAMPOS_PERFIL);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $datos = Validador::limpiar($_POST, Validador::CAMPOS_PERFIL);
            if (!Sesion::validarCsrf((string) ($_POST['csrf'] ?? ''))) {
                $errores[] = 'El formulario ha caducado. Inténtalo de nuevo.';
            } else {
                $errores = Validador::perfil($datos);
                if (!$errores && Usuario::emailExiste($datos['email'], (int) $usuario['id_usuario'])) {
                    $errores[] = 'Ya existe otra cuenta con ese email.';
                }
            }
            if (!$errores) {
                try {
                    Usuario::actualizarPerfil((int) $usuario['id_usuario'], $datos);
                    Sesion::ponerExito('Perfil actualizado.');
                    Vista::redirigir('perfil.php');
                } catch (PDOException $e) {
                    if ($e->getCode() !== '23000') {
                        throw $e;
                    }
                    $errores[] = 'Ya existe otra cuenta con ese email.';
                }
            }
        }

        Vista::render('perfil/ver', [
            'errores' => $errores,
            'valores' => $datos,
            'nick'    => $usuario['nick'],
            'rol'     => $usuario['rol'],
            'edad'    => Usuario::edad($usuario['fecha_nacimiento']),
            'alta'    => substr((string) $usuario['fecha_alta'], 0, 10),
            'hoy'     => date('Y-m-d'),
        ], 'Mi perfil');
    }

    public function cambiarPassword(): void
    {
        Sesion::exigirIdentificado();
        $usuario = $this->usuarioActual();
        $errores = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $actual = (string) ($_POST['actual'] ?? '');
            $nueva = (string) ($_POST['password'] ?? '');
            $repetida = (string) ($_POST['password2'] ?? '');

            if (!Sesion::validarCsrf((string) ($_POST['csrf'] ?? ''))) {
                $errores[] = 'El formulario ha caducado. Inténtalo de nuevo.';
            } elseif (!password_verify($actual, $usuario['password_hash'])) {
                $errores[] = 'La contraseña actual no es correcta.';
            } else {
                $errores = Validador::password($nueva, $repetida);
                if (!$errores && hash_equals($actual, $nueva)) {
                    $errores[] = 'La contraseña nueva debe ser distinta de la actual.';
                }
            }
            if (!$errores) {
                Usuario::cambiarPassword((int) $usuario['id_usuario'], $nueva);
                Sesion::ponerExito('Contraseña cambiada.');
                Vista::redirigir('perfil.php');
            }
        }

        Vista::render('perfil/password', ['errores' => $errores], 'Cambiar contraseña');
    }

    // Baja del usuario: se elimina su cuenta y toda su información. Pide la contraseña para confirmar.
    public function baja(): void
    {
        Sesion::exigirIdentificado();
        $usuario = $this->usuarioActual();
        $errores = [];
        $ultimoAdmin = $usuario['rol'] === 'administrador' && Usuario::contarAdministradores() <= 1;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Sesion::validarCsrf((string) ($_POST['csrf'] ?? ''))) {
                $errores[] = 'El formulario ha caducado. Inténtalo de nuevo.';
            } elseif ($ultimoAdmin) {
                $errores[] = 'Eres el único administrador: crea otro antes de darte de baja.';
            } elseif (!password_verify((string) ($_POST['password'] ?? ''), $usuario['password_hash'])) {
                $errores[] = 'La contraseña no es correcta.';
            } else {
                Usuario::eliminar((int) $usuario['id_usuario']);
                Sesion::cerrar();
                Sesion::ponerExito('Tu cuenta y todos tus datos se han eliminado.');
                Vista::redirigir('login.php');
            }
        }

        Vista::render('perfil/baja', ['errores' => $errores, 'ultimoAdmin' => $ultimoAdmin], 'Darme de baja');
    }

    // Datos actuales del usuario. Si su cuenta ya no existe (p. ej. la borró un administrador), se cierra la sesión.
    private function usuarioActual(): array
    {
        $usuario = Usuario::porId((int) Sesion::usuario()['id']);
        if ($usuario === null) {
            Sesion::cerrar();
            Sesion::ponerAviso('Tu cuenta ya no existe.');
            Vista::redirigir('login.php');
        }
        return $usuario;
    }
}
