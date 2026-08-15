<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Models\StudentPhotoModerationLog;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Personal autorizado únicamente (coordinator/rector, students.photo.moderate).
 * Borra la foto actual (si había) Y activa el bloqueo -- las dos cosas en la
 * misma acción, no dos pasos separados (decisión confirmada). El bloqueo es
 * PERMANENTE hasta que personal lo revierta explícitamente con
 * UnblockStudentPhotoUploadsAction -- nunca automático ni transitorio.
 */
final class BlockStudentPhotoUploadsAction
{
    public function execute(User $actor, User $student): void
    {
        Gate::forUser($actor)->authorize('moderatePhoto', $student);

        if ($student->photo_path !== null) {
            Storage::disk($student->photo_disk)->delete($student->photo_path);
        }

        $student->forceFill([
            'photo_disk' => null,
            'photo_path' => null,
            'photo_upload_blocked' => true,
        ])->save();

        StudentPhotoModerationLog::create([
            'student_id' => $student->id,
            'action' => 'blocked',
            'performed_by_user_id' => $actor->id,
        ]);
    }
}
