<?php

declare(strict_types=1);

namespace App\Modules\Shared\Actions;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Interfaces\ImageManagerInterface;

/**
 * Redimensiona y recomprime cualquier foto subida (galería, foro): ancho
 * máximo 1600px (suficiente para verse en pantalla, nunca pensado para
 * impresión) y siempre normalizada a JPEG calidad 72 -- las fotos de celular
 * reales pesan típicamente 3-8MB, esto las deja en un rango mucho menor sin
 * pérdida visible en la plataforma. cPanel de producción tiene solo 2.44 GB
 * de cuota total (ver CLAUDE.md "Producción real") -- comprimir no es
 * opcional aquí.
 *
 * Normalizar todo a JPEG (se descarta transparencia, fondo blanco) evita
 * tener que lidiar con las opciones de codificación distintas de cada
 * formato (PngEncoder no acepta "quality", por ejemplo) -- son fotografías
 * reales, no capturas de pantalla con canal alfa.
 *
 * strip: true (además del "strip" global en config/intervention-image.php,
 * aquí explícito para no depender de ese default) elimina metadata EXIF al
 * codificar -- incluye GPS si el teléfono lo grabó. Dato de menores: no hay
 * razón para conservar la ubicación exacta donde se tomó una foto que va a
 * verse en la plataforma.
 *
 * MEMORIA: el driver GD decodifica el bitmap COMPLETO sin comprimir en RAM
 * (ancho × alto × 4 bytes) antes de poder redimensionar nada, y el paso de
 * auto-orientación/fondo de Intervention clona ese buffer una vez más --
 * probado en vivo que una foto de celular real de ~3000px+ de ancho agota
 * el memory_limit por defecto de PHP (128M).
 *
 * IMPORTANTE -- esto es una decisión DISTINTA e independiente del
 * memory_limit=512M de phpunit.xml (ver comentario ahí): ese es exclusivo
 * del proceso de PHPUnit corriendo 350+ tests en el mismo proceso, y no debe
 * leerse como "512M es lo que hay que pedirle al hosting" -- son dos
 * decisiones separadas que solo comparten el número por coincidencia.
 *
 * Confirmado en vivo el 2026-08-09 contra el MultiPHP INI Editor real de
 * cPanel (ver CLAUDE.md "Producción real", punto 6): memory_limit para
 * academia.liceoinnovarte.edu.co está en 1G, con margen amplio sobre los
 * 512M de abajo. Aun así, las dos capas siguientes no dependen de que ese
 * número no cambie -- si el hosting alguna vez otorgara menos:
 *
 * 1. raiseMemoryLimitIfPossible(): intenta subir el límite a DESIRED_MEMORY_
 *    LIMIT vía ini_set(). Es un best-effort silencioso -- si el hosting no
 *    lo permite (memory_limit fijo por encima del cual ini_set() no tiene
 *    efecto, o disable_functions bloqueando ini_set), esta llamada
 *    simplemente no logra nada y el código sigue con lo que haya disponible
 *    de verdad, no con lo que se pidió.
 * 2. assertFitsInAvailableMemory(): por eso es la capa real de seguridad --
 *    lee las dimensiones de la imagen con getimagesize() (barato, no
 *    decodifica el bitmap), calcula la memoria que GD va a necesitar de
 *    verdad, y la compara contra el memory_limit ACTUAL (haya subido o no
 *    en el paso 1). Si no alcanza, rechaza con un error de validación común
 *    y corriente en vez de un Fatal Error de PHP no capturable -- funciona
 *    igual de bien con 128M, 256M o 512M, sea cual sea el límite real que
 *    otorgue el hosting.
 */
final class CompressUploadedImageAction
{
    private const MAX_WIDTH = 1600;

    private const JPEG_QUALITY = 72;

    private const DESIRED_MEMORY_LIMIT = '512M';

    /**
     * Multiplicador de seguridad sobre el bitmap crudo (ancho × alto × 4
     * bytes): cubre el buffer decodificado MÁS el clon que hace
     * Intervention en el paso de auto-orientación/fondo, con margen
     * adicional para el resto de lo que ya esté corriendo en el mismo
     * request. Deliberadamente conservador -- prefiere rechazar una foto
     * que técnicamente hubiera entrado, a arriesgar un Fatal Error.
     */
    private const MEMORY_SAFETY_FACTOR = 4;

    public function __construct(private readonly ImageManagerInterface $images) {}

    /**
     * $sourcePath es el archivo tal como lo dejó el mecanismo de subida
     * (Filament FileUpload o WithFileUploads de Livewire); $targetPath es
     * dónde debe quedar el resultado normalizado a .jpg. Si difieren, el
     * archivo original se borra tras comprimir -- no se dejan dos copias.
     *
     * $maxWidth es opcional (default: MAX_WIDTH, 1600px, el ancho pensado
     * para imágenes de contenido como galería/foro) -- un llamador puede
     * pedir un ancho menor para casos que son un ícono, no una imagen de
     * contenido (ej. foto de perfil de estudiante, 500px), sin duplicar el
     * resto del pipeline de compresión/seguridad de memoria.
     *
     * @throws ValidationException si la imagen es demasiado grande para
     *         procesarse con la memoria realmente disponible.
     */
    public function execute(string $disk, string $sourcePath, string $targetPath, int $maxWidth = self::MAX_WIDTH): void
    {
        $storage = Storage::disk($disk);
        $absolutePath = $storage->path($sourcePath);
        $previousMemoryLimit = ini_get('memory_limit');

        try {
            $this->raiseMemoryLimitIfPossible();
            $this->assertFitsInAvailableMemory($absolutePath);

            $image = $this->images->decodePath($absolutePath);
            $image->scaleDown(width: $maxWidth);

            $encoded = $image->encodeUsingFileExtension('jpg', quality: self::JPEG_QUALITY, strip: true);

            $storage->put($targetPath, (string) $encoded);

            if ($targetPath !== $sourcePath) {
                $storage->delete($sourcePath);
            }
        } finally {
            ini_set('memory_limit', $previousMemoryLimit);
        }
    }

    private function raiseMemoryLimitIfPossible(): void
    {
        ini_set('memory_limit', self::DESIRED_MEMORY_LIMIT);
    }

    private function assertFitsInAvailableMemory(string $absolutePath): void
    {
        $dimensions = @getimagesize($absolutePath);

        if ($dimensions === false) {
            throw ValidationException::withMessages([
                'photo' => 'El archivo no es una imagen válida.',
            ]);
        }

        [$width, $height] = $dimensions;
        $estimatedBytes = $width * $height * 4 * self::MEMORY_SAFETY_FACTOR;
        $availableBytes = $this->availableMemoryBytes();

        if ($availableBytes !== null && $estimatedBytes > $availableBytes) {
            throw ValidationException::withMessages([
                'photo' => "Esta foto es demasiado grande para procesarse ({$width}x{$height}px). Probá con una foto de menor resolución.",
            ]);
        }
    }

    /**
     * null si memory_limit está desactivado (-1, sin techo) -- no hay nada
     * que chequear en ese caso.
     */
    private function availableMemoryBytes(): ?int
    {
        $limit = ini_get('memory_limit');

        if ($limit === false || $limit === '-1') {
            return null;
        }

        return $this->parseIniMemoryValue($limit) - memory_get_usage(true);
    }

    private function parseIniMemoryValue(string $value): int
    {
        $unit = strtolower(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => (int) $value,
        };
    }
}
