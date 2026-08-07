<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Policies;

use App\Models\User;
use App\Modules\Assessment\Models\Submission;
use App\Modules\Project\Policies\ProjectPolicy;

/**
 * create() reutiliza ProjectPolicy::update() sobre el Project asociado a la
 * entrega (Submission -> ExpectedEvidence -> Phase -> Project) — no duplica
 * la lógica own/all, delega en la Policy dueña de Project. Un docente con
 * submissions.evaluate global solo puede evaluar entregas de proyectos donde
 * ya tendría permiso de editar (el suyo, o cualquiera si tiene .all).
 */
class EvaluationPolicy
{
    public function create(User $user, Submission $submission): bool
    {
        if (! $user->hasPermissionTo('submissions.evaluate')) {
            return false;
        }

        $project = $submission->expectedEvidence->phase->project;

        return app(ProjectPolicy::class)->update($user, $project);
    }
}
