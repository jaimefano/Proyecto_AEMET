<?php
declare(strict_types=1);

// Recuperación de contraseña por email:
//   1. El usuario escribe su email en recuperar.php.
//   2. El sistema le envía un correo con un enlace a restablecer.php?token=...
//   3. Con ese enlace elige una contraseña nueva. El enlace caduca y solo sirve una vez.
final class RecuperacionController
{
    public function solicitar(): void
    {
        $errores = [];
        $email = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));

            if (!Sesion::validarCsrf((string) ($_POST['csrf'] ?? ''))) {
                $errores[] = 'El formulario ha caducado. Inténtalo de nuevo.';
            } elseif ($email === '' || mb_strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errores[] = 'Introduce un email válido.';
            } else {
                $usuario = Usuario::porEmail($email);
                if ($usuario !== null) {
                    $this->enviarEnlace($usuario);
                }
                // La respuesta es la misma exista o no el email: no se revela qué cuentas existen.
                Sesion::ponerExito('Si ese email está registrado, te hemos enviado un correo con el enlace '
                    . 'para elegir una contraseña nueva. Revisa también la carpeta de spam.');
                Vista::redirigir('login.php');
            }
        }

        Vista::render('recuperar/solicitar', ['errores' => $errores, 'email' => $email], 'Recuperar contraseña');
    }

    public function restablecer(): void
    {
        header('Referrer-Policy: no-referrer');   // el token va en la URL: que no salga hacia otras webs

        $token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
        $solicitud = preg_match('/^[a-f0-9]{64}$/', $token)
            ? Recuperacion::buscarValida(hash('sha256', $token))
            : null;

        if ($solicitud === null) {
            Vista::render('recuperar/restablecer', ['tokenInvalido' => true, 'errores' => []], 'Restablecer contraseña');
            return;
        }

        $errores = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $password = (string) ($_POST['password'] ?? '');
            $repetida = (string) ($_POST['password2'] ?? '');

            if (!Sesion::validarCsrf((string) ($_POST['csrf'] ?? ''))) {
                $errores[] = 'El formulario ha caducado. Vuelve a abrir el enlace del correo.';
            } else {
                $errores = Validador::password($password, $repetida);
            }
            if (!$errores) {
                $idUsuario = (int) $solicitud['id_usuario'];
                Usuario::cambiarPassword($idUsuario, $password);
                Recuperacion::eliminarDeUsuario($idUsuario);   // el enlace solo sirve una vez
                Sesion::ponerExito('Contraseña cambiada. Ya puedes iniciar sesión con la nueva.');
                Vista::redirigir('login.php');
            }
        }

        Vista::render('recuperar/restablecer', [
            'tokenInvalido' => false,
            'token'         => $token,
            'errores'       => $errores,
        ], 'Restablecer contraseña');
    }

    private function enviarEnlace(array $usuario): void
    {
        $id = (int) $usuario['id_usuario'];
        $ultima = Recuperacion::ultimaCreada($id);
        if ($ultima !== null && time() - $ultima < (int) Config::get('recuperacion.espera')) {
            return;   // ya se envió uno hace un momento: evita inundar de correos a un usuario
        }

        $token = bin2hex(random_bytes(32));   // 256 bits aleatorios; en la base de datos solo se guarda su hash
        $validez = (int) Config::get('recuperacion.validez');
        Recuperacion::crear($id, hash('sha256', $token), $validez);

        $html = Vista::fragmento('correo/recuperacion', [
            'nombreApp' => Config::get('app.nombre'),
            'nick'      => $usuario['nick'],
            'enlace'    => Config::get('app.url') . '/restablecer.php?token=' . $token,
            'minutos'   => intdiv($validez, 60),
        ]);
        Correo::enviar($usuario['email'], 'Recupera tu contraseña · ' . Config::get('app.nombre'), $html);
    }
}
