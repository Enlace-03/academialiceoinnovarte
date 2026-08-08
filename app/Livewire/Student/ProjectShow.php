<?php

declare(strict_types=1);

namespace App\Livewire\Student;

use App\Modules\Project\Models\ExpectedEvidence;
use App\Modules\Project\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Solo lectura (Hito 3b-1): fases, guías, recursos y el estado de las
 * propias evidencias esperadas del estudiante. Subir evidencia queda fuera
 * de alcance (ver TODO.md) — la entrega la sigue registrando el docente.
 *
 * Sin Policies propias para Phase/Guide/Resource/ExpectedEvidence (decisión
 * confirmada del Hito 3b-1): se autoriza una sola vez a nivel de Project
 * (mount) y se consultan los hijos directamente, mismo criterio que ya usan
 * ForumThreadPolicy/ForumPostPolicy.
 *
 * hasRole('student') explícito (Hito 3b-2, mismo patrón que GroupChat):
 * válido sin ambigüedad porque el mecanismo de entrega de sesión es
 * auth-switch real (Auth::loginUsingId) -- auth()->user() durante una
 * sesión entregada ES el estudiante, no hay "delegación" que distinguir.
 */
#[Layout('layouts.portal')]
class ProjectShow extends Component
{
    public Project $project;

    public function mount(Project $project): void
    {
        abort_unless(auth()->user()->hasRole('student'), 403);

        $this->authorize('view', $project);

        $this->project = $project;
    }

    public function phases(): Collection
    {
        return $this->project->phases()
            ->with([
                'guides.resources',
                'resources' => fn ($query) => $query->whereNull('guide_id'),
                'expectedEvidences.rubric',
            ])
            ->get();
    }

    /**
     * @return array{status: string, level?: \App\Modules\Assessment\Models\RubricLevel|null, feedback?: string|null}
     */
    public function evidenceStatus(ExpectedEvidence $evidence): array
    {
        $submission = $evidence->submissions()
            ->where('student_id', auth()->id())
            ->with(['evaluations' => fn ($query) => $query->where('evaluator_type', 'teacher')])
            ->first();

        if ($submission === null) {
            return ['status' => 'pendiente'];
        }

        $evaluation = $submission->evaluations->first();

        if ($evaluation === null) {
            return ['status' => 'entregada'];
        }

        return [
            'status' => 'evaluada',
            'level' => $evaluation->consolidatedLevel(),
            'feedback' => $evaluation->feedback,
        ];
    }

    public function render()
    {
        return view('livewire.student.project-show', [
            'phases' => $this->phases(),
        ]);
    }
}
