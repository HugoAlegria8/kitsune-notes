<?php

declare(strict_types=1);

namespace KitsuneNotes\Support;

use DateTimeImmutable;

/**
 * Construye un mensaje de correo estándar (RFC 5322 / MIME) a partir de una
 * fila del buzón de pruebas. Sirve para dos cosas: descargarlo como fichero
 * .eml (abrible en Thunderbird, Outlook o Apple Mail) y entregarlo a un
 * servidor SMTP cuando el envío real está activado.
 *
 * Es un mensaje multipart/alternative con la versión en texto plano y la
 * versión HTML, ambas en UTF-8 y codificadas en quoted-printable.
 */
final class MimeMessage
{
    /**
     * @param array<string, mixed> $mail      fila de mail_outbox con sus cuerpos
     * @param bool                 $simulated true si el mensaje no sale de la aplicación; la
     *                                        cabecera X-Kitsune-Prototipo lo dice con exactitud
     */
    public static function build(array $mail, bool $simulated = true): string
    {
        $boundary = 'kn_' . substr(hash('sha256', (string) $mail['message_id']), 0, 24);
        $date     = (new DateTimeImmutable((string) $mail['created_at']))->format(DATE_RFC2822);

        $headers = [
            'Message-ID: <' . self::line((string) $mail['message_id']) . '>',
            'Date: ' . $date,
            'From: ' . self::address((string) $mail['from_name'], (string) $mail['from_email']),
            'To: ' . self::address((string) $mail['to_name'], (string) $mail['to_email']),
            'Subject: ' . self::encode((string) $mail['subject']),
            'MIME-Version: 1.0',
            'X-Kitsune-Prototipo: ' . ($simulated
                ? 'simulado - este mensaje no se ha entregado a ningun buzon real'
                : 'prototipo academico sin actividad comercial real - pedido y factura ficticios'),
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];

        $body = '--' . $boundary . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
            . quoted_printable_encode(self::crlf((string) $mail['body_text'])) . "\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
            . quoted_printable_encode(self::crlf((string) $mail['body_html'])) . "\r\n"
            . '--' . $boundary . "--\r\n";

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    /** Elimina saltos de línea y caracteres de control: evita la inyección de cabeceras. */
    private static function line(string $value): string
    {
        return trim((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value));
    }

    private static function crlf(string $text): string
    {
        return str_replace(["\r\n", "\r", "\n"], "\r\n", $text);
    }

    /** Codifica una cabecera con caracteres no ASCII (RFC 2047). */
    private static function encode(string $value): string
    {
        $value = self::line($value);

        if (preg_match('/^[\x20-\x7E]*$/', $value) === 1) {
            return $value;
        }

        return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
    }

    private static function address(string $name, string $email): string
    {
        $email = self::line($email);
        $name  = self::line($name);

        if ($name === '') {
            return '<' . $email . '>';
        }

        if (preg_match('/^[\x20-\x7E]*$/', $name) === 1) {
            return '"' . addcslashes($name, '"\\') . '" <' . $email . '>';
        }

        return mb_encode_mimeheader($name, 'UTF-8', 'B', "\r\n") . ' <' . $email . '>';
    }
}
