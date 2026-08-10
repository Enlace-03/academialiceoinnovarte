<?php

declare(strict_types=1);

namespace App\Modules\Community\Concerns;

use App\Modules\Shared\Actions\CompressUploadedImageAction;
use Illuminate\Support\Facades\Storage;

/**
 * Compartido por GalleryPhoto y ForumPostPhoto: mismo criterio de
 * compresión y de limpieza de archivo físico para ambas (item 6 del hito de
 * galería -- "mismo patrón de columnas... con compresión igual").
 *
 * Se dispara en created() (no antes) porque necesita $photo->uuid, que
 * HasUuids ya asignó para ese momento, para nombrar el archivo normalizado
 * de forma determinística: "{directorio}/{uuid}.jpg" -- reemplaza lo que
 * haya dejado el mecanismo de subida original (nombre aleatorio, extensión
 * original) sin importar si venía de Filament FileUpload o de
 * WithFileUploads de Livewire.
 *
 * saveQuietly() al actualizar file_path evita reentrar en este mismo
 * created() -- created() no se dispara en updates, pero se usa quietly de
 * todas formas para no disparar ningún otro observer/evento sobre un update
 * que es puramente mecánico (no es un "hecho de dominio").
 *
 * deleting(): ninguno de los dos modelos usa SoftDeletes (a diferencia de
 * GalleryPost/ForumPost) -- un delete() sobre la fila es siempre físico
 * (por ejemplo, Filament quita una foto del Repeater y sincroniza la
 * relación), así que este es el único punto donde el archivo puede
 * limpiarse sin arriesgar borrar algo que un soft-delete todavía necesita
 * mostrar. Relevante de verdad: cPanel de producción tiene solo 2.44 GB de
 * cuota total, un archivo huérfano por cada foto removida se acumula.
 */
trait CompressesPhotoUploads
{
    protected static function bootCompressesPhotoUploads(): void
    {
        static::created(function (self $photo) {
            $directory = dirname($photo->file_path);
            $targetPath = ($directory === '.' ? '' : "{$directory}/").$photo->uuid.'.jpg';

            app(CompressUploadedImageAction::class)->execute($photo->file_disk, $photo->file_path, $targetPath);

            $photo->file_path = $targetPath;
            $photo->saveQuietly();
        });

        static::deleting(function (self $photo) {
            Storage::disk($photo->file_disk)->delete($photo->file_path);
        });
    }
}
