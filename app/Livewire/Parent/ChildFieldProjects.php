<?php

declare(strict_types=1);

namespace App\Livewire\Parent;

use App\Models\User;
use App\Modules\Assessment\Models\RubricLevel;
use App\Modules\Institution\Models\ThinkingField;
use App\Modules\Project\Models\Project;
use App\Modules\Tracking\Models\PerformanceSnapshot;
use App\Modules\Tracking\Models\StudentProgress;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Segundo nivel del drill-down: proyectos publicados que tocan el campo de
 * pensamiento seleccionado y a los que $child tiene acceso (mismo cycle_id
 * que MyProjects::projects() del estudiante, pero contra $child en vez de
 * auth()->user()), cada uno con su barra de avance + nivel cualitativo --
 * mismos dos indicadores separados que ProjectShow::progressSummary(),
 * reconstruidos acá contra $child (decisión de la auditoría: no se toca
 * ProjectShow para generalizarlo a un estudiante objetivo).
 *
 * Solo lectura: mount()/projects()/progressSummary()/render() -- ninguno
 * muta nada.
 */
#[Layout('layouts.portal')]
class ChildFieldProjects extends Component
{
    public User $child;

    public ThinkingField $field;

    public function mount(User $child, ThinkingField $field): void
    {
        abort_unless(auth()->user()->hasRole('parent') && auth()->user()->isGuardianOf($child), 403);

        $this->child = $child;
        $this->field = $field;
    }

    public function projects(): Collection
    {
        $cycleId = $this->child->schoolGrade?->cycle_id;

        if ($cycleId === null) {
            return new Collection();
        }

        return Project::query()
            ->where('cycle_id', $cycleId)
            ->where('status', 'published')
            ->whereHas('thinkingFields', fn ($query) => $query->whereKey($this->field->id))
            ->orderByDesc('year')
            ->orderByDesc('semester')
            ->orderBy('title')
            ->get();
    }

    /**
     * @return array{pct: int, level: ?RubricLevel}
     */
    public function progressSummary(Project $project): array
    {
        $progress = StudentProgress::query()
            ->where('student_id', $this->child->id)
            ->where('project_id', $project->id)
            ->whereNull('phase_id')
            ->first();

        $snapshot = PerformanceSnapshot::query()
            ->where('student_id', $this->child->id)
            ->where('project_id', $project->id)
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
        return view('livewire.parent.child-field-projects', [
            'projects' => $this->projects(),
        ]);
    }
}
