<?php

declare(strict_types=1);

namespace App\Modules\Project\Actions;

use App\Models\User;
use App\Modules\Assessment\Models\Submission;
use App\Modules\Project\Models\StudentPhaseSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Evidencias esperadas de un estudiante con StudentPhaseSchedule dentro del
 * rango [$from, $to] que todavía no tiene Submission en submitted/evaluated.
 * Único punto de este cruce (StudentPhaseSchedule + ExpectedEvidence,
 * excluyendo lo ya resuelto): antes vivía duplicado como método privado en
 * PortalHome (evidencias del hijo, sin límite superior) y se hubiera
 * triplicado con MyProjects ("próxima entrega", ventana de 7 días) y
 * MyCalendar (mes visible completo, incluyendo días ya pasados para marcar
 * "vencido"). $from/$to son fechas simples (comparadas por ->toDateString()
 * contra la columna end_date), no distinguen hora del día.
 */
class ResolvePendingEvidencesForStudentAction
{
    /**
     * @return Collection<int, array{evidence: \App\Modules\Project\Models\ExpectedEvidence, project: \App\Modules\Project\Models\Project, phase_name: string, due_date: Carbon}>
     */
    public function execute(User $student, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $from ??= now();

        $query = StudentPhaseSchedule::query()
            ->where('student_id', $student->id)
            ->where('end_date', '>=', $from->toDateString())
            ->with('phase.project', 'phase.expectedEvidences');

        if ($to !== null) {
            $query->where('end_date', '<=', $to->toDateString());
        }

        $schedules = $query->orderBy('end_date')->get();

        $resolvedEvidenceIds = Submission::query()
            ->where('student_id', $student->id)
            ->whereIn('status', ['submitted', 'evaluated'])
            ->pluck('expected_evidence_id');

        return $schedules
            ->flatMap(fn (StudentPhaseSchedule $schedule) => $schedule->phase->expectedEvidences
                ->reject(fn ($evidence) => $resolvedEvidenceIds->contains($evidence->id))
                ->map(fn ($evidence) => [
                    'evidence' => $evidence,
                    'project' => $schedule->phase->project,
                    'phase_name' => $schedule->phase->name,
                    'due_date' => $schedule->end_date,
                ]))
            ->sortBy('due_date')
            ->values();
    }
}
