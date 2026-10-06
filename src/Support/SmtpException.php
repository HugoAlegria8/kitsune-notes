<?php

declare(strict_types=1);

namespace KitsuneNotes\Support;

use RuntimeException;

/**
 * Fallo al hablar con el servidor SMTP.
 *
 * El mensaje está pensado para mostrarse al personal del back-office y
 * para el registro de errores: describe qué paso falló y qué respondió el
 * servidor, pero nunca contiene la contraseña ni las credenciales.
 */
final class SmtpException extends RuntimeException
{
}
