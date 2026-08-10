<?php

declare(strict_types=1);

namespace Database\Factories\Concerns;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * GalleryPhoto/ForumPostPhoto disparan una compresión real (Intervention
 * Image) en su evento created() -- ver CompressesPhotoUploads -- así que la
 * factory necesita dejar un archivo de imagen JPEG *válido y decodificable*
 * en el disco ANTES de crear la fila, no solo un string de ruta falso.
 * Genera un JPEG real y minúsculo vía GD directamente (sin depender de un
 * fixture en el repositorio). Los tests que usan estas factories deben
 * llamar Storage::fake($disk) en su setUp() para no escribir en el
 * almacenamiento real.
 */
trait CreatesFakeImageFile
{
    protected function putFakeImage(string $disk, string $directory, int $size = 40): string
    {
        $path = "{$directory}/".Str::random(20).'.jpg';

        $image = imagecreatetruecolor($size, $size);
        imagefill($image, 0, 0, imagecolorallocate($image, 120, 160, 200));
        ob_start();
        imagejpeg($image);
        $binary = ob_get_clean();
        imagedestroy($image);

        Storage::disk($disk)->put($path, $binary);

        return $path;
    }
}
