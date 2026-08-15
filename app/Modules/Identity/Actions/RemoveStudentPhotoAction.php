<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Models\StudentPhotoModerationLog;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Dos actores posibles para la misma acción (decisión confirmada, una sola
 * Action, no dos): el propio acudiente sobre su hijo, o personal autorizado
 * (students.photo.moderate) sobre cualquier estudiante. Solo la segunda
 * rama queda auditada en student_photo_moderation_log -- el acudiente
 * quitando su propia foto no es una intervención de personal, no hay nada
 * que auditar ahí más allá del updated_at de la propia columna.
 *
 * Un rol nunca combina "es acudiente de este estudiante" con
 * "tiene students.photo.moderate" (roles de identidad y de personal son
 * mutuamente excluyentes, ver ExclusiveIdentityRoleRule) -- las dos ramas
 * de abajo son estrictamente disjuntas en la práctica.
 */
final class RemoveStudentPhotoAction
{
    public function execute(User $actor, User $student): void
    {
        $actingAsModerator = Gate::forUser($actor)->allows('moderatePhoto', $student);

        if (! $actingAsModerator) {
            Gate::forUser($actor)->authorize('removeOwnPhoto', $student);
        }

        if ($student->photo_path !== null) {
            Storage::disk($student->photo_disk)->delete($student->photo_path);

            $student->forceFill(['photo_disk' => null, 'photo_path' => null])->save();
        }

        if ($actingAsModerator) {
            StudentPhotoModerationLog::create([
                'student_id' => $student->id,
                'action' => 'removed',
                'performed_by_user_id' => $actor->id,
            ]);
        }
    }
}
