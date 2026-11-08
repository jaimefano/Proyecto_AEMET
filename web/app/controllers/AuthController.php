<?php
declare(strict_types=1);

// Registro, inicio y cierre de sesión (RQ de usuarios del ERS).
final class AuthController
{
    private const CAMPOS_REGISTRO = ['nick', 'nombre', 'apellidos', 'fecha_nacimiento', 'telefono', 'email'];

    public function registro(): void
    {
        if (Sesion::estaIdentificado()) {
            Vista::redirigir('index.php');
        }
        $errores = [];
        $datos = Validador::limpiar([], self::CAMPOS_REGISTRO);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $datos = Validador::limpiar($_POST, self::CAMPOS_REGISTRO);
            $password = (string) ($_POST['password'] ?? '');
            $repetida = (string) ($_POST['password2'] ?? '');

            if (!Sesion::validarCsrf((string) ($_POST['csrf'] ?? ''))) {
                $errores[] = 'El formulario ha caducado. Inténtalo de nuevo.';
            } else {
                $errores = array_merge(
                    Validador::nick($datos['nick']),
                    Validador::password($password, $repetida),
                    Validador::perfil($datos)
                );
                if (!$errores) {
                    $errores = $this->comprobarUnicos($datos);
                }
                if (!$errores) {
                    try {
                        Usuario::crear($datos, $password);
                        Sesion::ponerExito('Registro completado. Ya puedes iniciar sesión.');
                        Vista::redirigir('login.php');
                    } catch (PDOException $e) {
                        if ($e->getCode() !== '23000') {   // 23000 = clave duplicada (otro registro simultáneo)
                            throw $e;
                        }
                        $errores[] = 'Ese nombre de usuario o ese email ya están registrados.';
                    }
                }
            }
        }

        Vista::render('auth/registro', [
            'errores' => $errores,
            'valores' => $datos,
            'hoy'     => date('Y-m-d'),
        ], 'Registro');
    }

    public function login(): void
    {
        if (Sesion::estaIdentificado()) {
            Vista::redirigir('index.php');
        }
        $errores = [];
        $nick = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nick = trim((string) ($_POST['nick'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');

            if (!Sesion::validarCsrf((string) ($_POST['csrf'] ?? ''))) {
                $errores[] = 'El formulario ha caducado. Inténtalo de nuevo.';
            } elseif ($nick === '' || $password === '') {
                $errores[] = 'Introduce tu nombre de usuario y tu contraseña.';
            } else {
                $usuario = Usuario::porNick($nick);
                if ($usuario === null) {
                    password_hash($password, PASSWORD_DEFAULT);   // mismo coste que una comprobación real
                } elseif (password_verify($password, $usuario['password_hash'])) {
                    Sesion::identificar($usuario);
                    Vista::redirigir('index.php');
                }
                // El mensaje es el mismo si falla el usuario o la contraseña: no revela qué usuarios existen.
                $errores[] = 'Nombre de usuario o contraseña incorrectos.';
            }
        }

        Vista::render('auth/login', ['errores' => $errores, 'nick' => $nick], 'Iniciar sesión');
    }

    public function logout(): void
    {
        if (Sesion::estaIdentificado()) {
            Sesion::cerrar();
            Sesion::ponerExito('Has cerrado la sesión.');
        }
        Vista::redirigir('login.php');
    }

    // Comprueba que el nick y el email no los use ya otra persona.
    private function comprobarUnicos(array $datos): array
    {
        $errores = [];
        if (Usuario::nickExiste($datos['nick'])) {
            $errores[] = 'Ese nombre de usuario ya está en uso.';
        }
        if (Usuario::emailExiste($datos['email'])) {
            $errores[] = 'Ya existe una cuenta con ese email.';
        }
        return $errores;
    }
}
