<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Models\StudentPhotoModerationLog;
use Illuminate\Support\Facades\Gate;

/**
 * Personal autorizado únicamente (coordinator/rector, students.photo.moderate).
 * Solo desactiva el flag -- no restaura ninguna foto (la que había ya se
 * borró al bloquear), el acudiente vuelve a poder subir una nueva desde cero.
 */
final class UnblockStudentPhotoUploadsAction
{
    public function execute(User $actor, User $student): void
    {
        Gate::forUser($actor)->authorize('moderatePhoto', $student);

        $student->forceFill(['photo_upload_blocked' => false])->save();

        StudentPhotoModerationLog::create([
            'student_id' => $student->id,
            'action' => 'unblocked',
            'performed_by_user_id' => $actor->id,
        ]);
    }
}
