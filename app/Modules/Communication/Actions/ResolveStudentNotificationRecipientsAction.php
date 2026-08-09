<?php

declare(strict_types=1);

namespace App\Modules\Communication\Actions;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Criterio único de ruteo por ciclo (Hito 5a/5b, extraído para no
 * duplicarlo entre SendSubmissionDeadlineRemindersAction y la notificación
 * de evaluación recibida): estudiante en ciclos 3-4 (Cycle::order >= 3)
 * recibe directo; en ciclos 1-2, cada acudiente vinculado recibe en su
 * lugar. Sin grado asignado, se trata como ciclo 1-2 (fail-closed hacia
 * acudientes, nunca asume acceso directo sin dato real).
 *
 * @return Collection<int, User>
 */
final class ResolveStudentNotificationRecipientsAction
{
    public function execute(User $student): Collection
    {
        $cycleOrder = $student->schoolGrade?->cycle?->order ?? 0;

        if ($cycleOrder >= 3) {
            return collect([$student]);
        }

        return $student->guardians;
    }
}
