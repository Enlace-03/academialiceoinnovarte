<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Policies;

use App\Models\User;
use App\Modules\Assessment\Models\Submission;
use App\Modules\Project\Models\ExpectedEvidence;
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
 *
 * Rama acudiente (drill-down): $submission->student_id ya identifica al
 * hijo objetivo directamente -- a diferencia de ProjectPolicy::view(), no
 * hace falta un método aparte tipo viewAsGuardian() con un parámetro extra,
 * el propio submission trae el contexto. Necesaria en la práctica para que
 * la ruta compartida submissions.attachments.show (que autoriza
 * exclusivamente contra esta Policy) sirva también los adjuntos ya
 * entregados dentro de ChildEvidenceShow, no solo dentro de EvidenceShow.
 *
 * create()/update() (Hito 3b-3, flujo de entrega del propio estudiante):
 * create() se autoriza contra ExpectedEvidence, no contra Submission -- la
 * primera entrega de un estudiante para esa evidencia todavía no tiene fila.
 * update() solo permite reentrega en status=returned (regla del pedido):
 * una entrega 'submitted' está en espera de evaluación (de solo lectura para
 * el estudiante) y una 'evaluated' ya no se puede modificar.
 */
class SubmissionPolicy
{
    public function view(User $user, Submission $submission): bool
    {
        if ($user->id === $submission->student_id) {
            return true;
        }

        if ($user->hasRole('parent') && $user->isGuardianOf($submission->student)) {
            return true;
        }

        $project = $submission->expectedEvidence->phase->project;

        return app(ProjectPolicy::class)->viewAsStaff($user, $project);
    }

    public function create(User $user, ExpectedEvidence $expectedEvidence): bool
    {
        return $user->hasRole('student')
            && $user->canAccessProject($expectedEvidence->phase->project);
    }

    public function update(User $user, Submission $submission): bool
    {
        return $user->id === $submission->student_id
            && $submission->status === 'returned';
    }
}
