<?php

declare(strict_types=1);

namespace App\Modules\Project\Actions;

use App\Modules\Project\Models\Project;

/**
 * Publicar/despublicar un proyecto (hito de separación vista/edición). Sin
 * evento de dominio: a diferencia de SubmissionRegistered/SubmissionEvaluated
 * (que sí tienen listeners reales en Tracking/Communication), nada en el
 * alcance de este hito consume "proyecto publicado" -- si en el futuro hace
 * falta notificar a los estudiantes del ciclo, se agrega el evento en ese
 * momento, no antes.
 *
 * Autorización: responsabilidad de quien invoca (ProjectPolicy::update(),
 * own/all ya existente) -- mismo criterio que el resto de Actions de este
 * proyecto, esta Action no vuelve a validar lo que el caller ya autorizó.
 */
final class SetProjectStatusAction
{
    public function execute(Project $project, string $status): Project
    {
        $project->update(['status' => $status]);

        return $project;
    }
}
