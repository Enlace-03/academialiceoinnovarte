<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Policies;

use App\Models\User;
use App\Modules\Assessment\Models\Submission;
use App\Modules\Project\Policies\ProjectPolicy;

/**
 * No existía hasta el Hito 3b-1: el personal nunca pasó por aquí (gestiona
 * entregas vía ExpectedEvidencesRelationManager, autorizando directo con
 * Gate::allows('update', ...) sobre el proyecto — ver ese RelationManager),
 * así que esta Policy es una puerta nueva, sin riesgo de romper Filament.
 *
 * view(): el propio estudiante siempre ve su entrega; el personal la ve si
 * puede ver el proyecto dueño. Usa ProjectPolicy::viewAsStaff(), NO view()
 * completo -- view() ya incluye la rama student (Hito 3b-1), y de usarse
 * aquí cualquier estudiante del mismo ciclo podría ver la entrega (y la
 * retroalimentación) de OTRO estudiante, no solo la propia.
 */
class SubmissionPolicy
{
    public function view(User $user, Submission $submission): bool
    {
        if ($user->id === $submission->student_id) {
            return true;
        }

        $project = $submission->expectedEvidence->phase->project;

        return app(ProjectPolicy::class)->viewAsStaff($user, $project);
    }
}
