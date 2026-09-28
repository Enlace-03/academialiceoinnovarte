<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Policies;

use App\Models\User;
use App\Modules\Assessment\Models\EvaluationAttachment;

/**
 * Documento de retroalimentación del docente. A propósito más angosto que
 * SubmissionPolicy::view(): esa deja pasar a cualquier staff que pueda ver el
 * proyecto (viewAsStaff), y aquí un docente distinto del que evaluó NO debe
 * verlo. Pueden: el estudiante dueño de la entrega, sus acudientes, el docente
 * que evaluó, y quien tenga observations.view.all (rector/coordinator,
 * supervisión de solo lectura).
 */
class EvaluationAttachmentPolicy
{
    public function view(User $user, EvaluationAttachment $attachment): bool
    {
        $submission = $attachment->evaluation->submission;

        if ($user->id === $submission->student_id) {
            return true;
        }

        if ($user->hasRole('parent') && $user->isGuardianOf($submission->student)) {
            return true;
        }

        if ($attachment->evaluation->evaluated_by === $user->id) {
            return true;
        }

        return $user->hasPermissionTo('observations.view.all');
    }
}
