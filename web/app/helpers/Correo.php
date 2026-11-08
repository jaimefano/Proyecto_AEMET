<?php
declare(strict_types=1);

// Envío de emails por SMTP, sin librerías externas. Lo usa la recuperación de contraseña.
// Configuración en ajustes.php (variables SMTP_* del .env). Admite STARTTLS (587), SSL (465) y, solo
// para pruebas locales, conexión sin cifrar. El mensaje va en HTML con una alternativa de texto.
final class Correo
{
    public static function configurado(): bool
    {
        return (string) Config::get('correo.host') !== '';
    }

    // $html es el cuerpo del mensaje; la versión de texto se obtiene de él. Devuelve true si el
    // servidor SMTP aceptó el mensaje. Nunca lanza excepciones: los fallos se anotan en el log.
    public static function enviar(string $para, string $asunto, string $html): bool
    {
        if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $texto = self::aTexto($html);

        if (!self::configurado()) {
            // Sin SMTP no se puede enviar nada: el contenido queda en el log del contenedor (solo desarrollo).
            error_log("Correo: SMTP sin configurar (define SMTP_HOST en el .env). Mensaje para $para:\n$texto");
            return false;
        }
        try {
            $remitente = (string) Config::get('correo.remitente');
            $mensaje = self::construir($para, $asunto, $texto, $html, $remitente);
            self::transmitir($para, $remitente, $mensaje);
            return true;
        } catch (Throwable $e) {
            error_log('Correo: no se pudo enviar el mensaje a ' . $para . ': ' . $e->getMessage());
            return false;
        }
    }

    // ---- Conversación SMTP ----

    private static function transmitir(string $para, string $remitente, string $mensaje): void
    {
        $host = (string) Config::get('correo.host');
        $puerto = (int) Config::get('correo.port');
        $seguridad = (string) Config::get('correo.seguridad');
        $usuario = (string) Config::get('correo.usuario');
        $password = (string) Config::get('correo.password');

        $contexto = stream_context_create(['ssl' => [
            'peer_name'        => $host,
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ]]);
        $destino = ($seguridad === 'ssl' ? 'ssl' : 'tcp') . "://$host:$puerto";
        $conexion = @stream_socket_client($destino, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $contexto);
        if ($conexion === false) {
            throw new RuntimeException("No se pudo conectar con $host:$puerto ($errstr)");
        }
        stream_set_timeout($conexion, 15);

        try {
            $yo = self::nombreLocal();
            self::esperar($conexion, [220]);
            self::ordenar($conexion, "EHLO $yo", [250]);
            if ($seguridad === 'tls') {
                self::ordenar($conexion, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($conexion, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('No se pudo activar el cifrado TLS con el servidor SMTP.');
                }
                self::ordenar($conexion, "EHLO $yo", [250]);
            }
            if ($usuario !== '') {
                self::ordenar($conexion, 'AUTH LOGIN', [334]);
                self::ordenar($conexion, base64_encode($usuario), [334]);
                self::ordenar($conexion, base64_encode($password), [235]);
            }
            self::ordenar($conexion, "MAIL FROM:<$remitente>", [250]);
            self::ordenar($conexion, "RCPT TO:<$para>", [250, 251]);
            self::ordenar($conexion, 'DATA', [354]);
            // Una línea que empiece por "." se duplica (dot-stuffing); el punto solo cierra el mensaje.
            fwrite($conexion, preg_replace('/^\./m', '..', $mensaje) . "\r\n.\r\n");
            self::esperar($conexion, [250]);
            try {
                self::ordenar($conexion, 'QUIT', [221]);
            } catch (Throwable) {
                // El mensaje ya está aceptado; da igual cómo se cierre la conexión.
            }
        } finally {
            fclose($conexion);
        }
    }

    private static function ordenar($conexion, string $orden, array $esperados): string
    {
        fwrite($conexion, $orden . "\r\n");
        return self::esperar($conexion, $esperados);
    }

    // Lee la respuesta del servidor (puede ocupar varias líneas "250-...") y comprueba su código.
    private static function esperar($conexion, array $esperados): string
    {
        $respuesta = '';
        do {
            $linea = fgets($conexion, 1024);
            if ($linea === false) {
                throw new RuntimeException('El servidor SMTP no responde.');
            }
            $respuesta .= $linea;
        } while (isset($linea[3]) && $linea[3] === '-');

        if (!in_array((int) substr($respuesta, 0, 3), $esperados, true)) {
            throw new RuntimeException('Respuesta SMTP inesperada: ' . trim($respuesta));
        }
        return $respuesta;
    }

    private static function nombreLocal(): string
    {
        $nombre = preg_replace('/[^A-Za-z0-9.-]/', '', (string) gethostname());
        return $nombre !== '' ? $nombre : 'localhost';
    }

    // ---- Construcción del mensaje (MIME, UTF-8, texto + HTML) ----

    private static function construir(string $para, string $asunto, string $texto, string $html, string $remitente): string
    {
        $limite = '=_' . bin2hex(random_bytes(12));
        $dominio = substr(strrchr($remitente, '@') ?: '@cca.local', 1);
        $cabeceras = [
            'Date: ' . date('r'),
            'From: ' . self::cabecera((string) Config::get('app.nombre')) . " <$remitente>",
            "To: <$para>",
            'Subject: ' . self::cabecera($asunto),
            'Message-ID: <' . bin2hex(random_bytes(12)) . "@$dominio>",
            'MIME-Version: 1.0',
            "Content-Type: multipart/alternative; boundary=\"$limite\"",
        ];
        $cuerpo = "--$limite\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($texto))
            . "--$limite\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html))
            . "--$limite--\r\n";
        return implode("\r\n", $cabeceras) . "\r\n\r\n" . $cuerpo;
    }

    // Codifica un texto para usarlo en una cabecera (admite tildes). Quita saltos de línea: evita la inyección de cabeceras.
    private static function cabecera(string $texto): string
    {
        return mb_encode_mimeheader(str_replace(["\r", "\n"], ' ', $texto), 'UTF-8', 'B', "\r\n");
    }

    // Versión de texto plano del HTML: párrafos y saltos de línea separados, sin etiquetas.
    private static function aTexto(string $html): string
    {
        $texto = preg_replace('#</(p|h[1-6]|li|div)>|<br\s*/?>#i', "\n\n", $html);
        $texto = html_entity_decode(strip_tags((string) $texto), ENT_QUOTES, 'UTF-8');
        $texto = preg_replace("/[ \t]+\n/", "\n", $texto);
        return trim((string) preg_replace("/\n{3,}/", "\n\n", $texto)) . "\n";
    }
}
