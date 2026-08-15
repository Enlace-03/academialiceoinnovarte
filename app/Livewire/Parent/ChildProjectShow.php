<?php

declare(strict_types=1);

namespace App\Livewire\Parent;

use App\Models\User;
use App\Modules\Assessment\Models\RubricLevel;
use App\Modules\Project\Models\ExpectedEvidence;
use App\Modules\Project\Models\Project;
use App\Modules\Tracking\Models\PerformanceSnapshot;
use App\Modules\Tracking\Models\StudentProgress;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Tercer nivel del drill-down: mismo contenido que Student\ProjectShow
 * (fases, guías, recursos, estado de evidencias) pero para $child --
 * componente nuevo y separado, no una adaptación de Student\ProjectShow
 * (decisión de la auditoría previa a este hito): ese componente ya pasó
 * varias rondas de revisión encontrando bugs reales, y su hermano
 * Student\EvidenceShow tiene métodos públicos de escritura (submit(),
 * startResubmission(), etc.) que generalizar a un "estudiante objetivo"
 * habría arriesgado innecesariamente. Este componente y su hermano
 * ChildEvidenceShow reconstruyen la misma lectura contra $child, sin tocar
 * ninguno de los dos componentes de estudiante.
 *
 * Sin el <livewire:student.forum-thread-list> que sí tiene ProjectShow --
 * el foro es una funcionalidad interactiva propia del estudiante, fuera
 * del alcance explícito de este drill-down (fases, guías, estado de
 * evidencias, retroalimentación).
 *
 * Solo lectura: ningún método público muta nada de $child ni de $project
 * (confirmado explícitamente por reflection en
 * ChildProjectShowTest::test_component_has_no_writable_public_methods()).
 */
#[Layout('layouts.portal')]
class ChildProjectShow extends Component
{
    public User $child;

    public Project $project;

    public function mount(User $child, Project $project): void
    {
        abort_unless(auth()->user()->hasRole('parent') && auth()->user()->isGuardianOf($child), 403);

        $this->authorize('viewAsGuardian', [$project, $child]);

        $this->child = $child;
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
     * @return array{status: string, level?: ?RubricLevel, feedback?: ?string}
     */
    public function evidenceStatus(ExpectedEvidence $evidence): array
    {
        $submission = $evidence->submissions()
            ->where('student_id', $this->child->id)
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

    /**
     * @return array{pct: int, level: ?RubricLevel}
     */
    public function progressSummary(): array
    {
        $progress = StudentProgress::query()
            ->where('student_id', $this->child->id)
            ->where('project_id', $this->project->id)
            ->whereNull('phase_id')
            ->first();

        $snapshot = PerformanceSnapshot::query()
            ->where('student_id', $this->child->id)
            ->where('project_id', $this->project->id)
            ->latest('snapshot_date')
            ->first();

        $levelKey = $snapshot?->metrics['qualitative_level_key'] ?? null;

        return [
            'pct' => $progress?->progress_pct ?? 0,
            'level' => $levelKey !== null ? RubricLevel::where('key', $levelKey)->first() : null,
        ];
    }

    public function render()
    {
        return view('livewire.parent.child-project-show', [
            'phases' => $this->phases(),
            'progressSummary' => $this->progressSummary(),
        ]);
    }
}
