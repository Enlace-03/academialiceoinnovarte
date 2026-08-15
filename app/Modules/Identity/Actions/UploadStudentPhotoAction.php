<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Shared\Actions\CompressUploadedImageAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Acudiente sube/cambia la foto de perfil de su propio hijo (ciclos 1-2
 * únicamente). Reutiliza CompressUploadedImageAction con un ancho máximo
 * más chico que el de galería/foro (500px -- es un ícono, no una imagen de
 * contenido). El path de destino es determinístico ("student-photos/
 * {uuid}.jpg"): volver a subir simplemente sobrescribe el archivo anterior,
 * sin acumular huérfanos -- relevante con la cuota de disco real de
 * producción (ver CLAUDE.md "Producción real").
 *
 * El bloqueo (photo_upload_blocked) se rechaza con un mensaje claro pero SIN
 * exponer el motivo (decisión confirmada) -- la UI del acudiente ya oculta
 * la opción por completo cuando está bloqueado (ver PortalHome), este chequeo
 * es la defensa en profundidad para una llamada directa a la Action.
 */
final class UploadStudentPhotoAction
{
    private const MAX_WIDTH = 500;

    private const DISK = 'local';

    public function execute(User $guardian, User $student, UploadedFile $file): void
    {
        Gate::forUser($guardian)->authorize('uploadPhoto', $student);

        if ($student->photo_upload_blocked) {
            throw ValidationException::withMessages([
                'photo' => 'La subida de fotos para este estudiante está deshabilitada.',
            ]);
        }

        $sourcePath = $file->store('student-photos/tmp', self::DISK);
        $targetPath = "student-photos/{$student->uuid}.jpg";

        app(CompressUploadedImageAction::class)->execute(self::DISK, $sourcePath, $targetPath, self::MAX_WIDTH);

        $student->forceFill([
            'photo_disk' => self::DISK,
            'photo_path' => $targetPath,
        ])->save();
    }
}
