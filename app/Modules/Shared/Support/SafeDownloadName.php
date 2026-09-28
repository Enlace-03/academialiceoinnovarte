<?php

declare(strict_types=1);

namespace App\Modules\Shared\Support;

/**
 * Nombre de descarga seguro para Content-Disposition a partir de un
 * original_filename guardado en BD (input de usuario). basename() descarta
 * cualquier ruta (../, directorios); se eliminan caracteres de control y
 * separadores restantes -- Symfony lanza InvalidArgumentException (500) si
 * el nombre trae "/" o "\".
 */
final class SafeDownloadName
{
    public static function for(?string $originalFilename, string $fallback = 'documento'): string
    {
        $name = basename(str_replace('\\', '/', (string) $originalFilename));
        $name = trim(preg_replace('/[\x00-\x1F\x7F\/\\\\]+/', '', $name) ?? '', ". \t");

        return $name !== '' ? $name : $fallback;
    }
}
