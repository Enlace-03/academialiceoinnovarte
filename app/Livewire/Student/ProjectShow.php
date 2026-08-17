<?php

declare(strict_types=1);

namespace App\Livewire\Student;

use App\Modules\Assessment\Models\RubricLevel;
use App\Modules\Project\Models\ExpectedEvidence;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTeam;
use App\Modules\Tracking\Models\PerformanceSnapshot;
use App\Modules\Tracking\Models\StudentProgress;
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
 *
 * maxWidth (hito de layout ancho): pide un contenedor más ancho al layout
 * portal vía el segundo parámetro de #[Layout] (mecanismo idiomático de
 * Livewire 3 -- BaseLayout::$params se pasa como datos al @component() de
 * la vista, ver vendor/livewire/livewire/src/Features/SupportPageComponents),
 * para que quepa el grid de dos columnas (fases | foro+chats) sin apretarlo
 * dentro del max-w-3xl por defecto del resto del portal.
 */
#[Layout('layouts.portal', ['maxWidth' => 'max-w-6xl'])]
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

    /**
     * Lee de las tablas ya precalculadas por Tracking (Hito 4), nunca
     * recalcula en vivo -- mismo criterio que evidenceStatus() con las
     * evaluaciones. Dos valores separados a propósito (progress_pct
     * mecánico + nivel cualitativo aparte), nunca fusionados en un número
     * que represente calidad.
     *
     * @return array{pct: int, level: ?RubricLevel}
     */
    public function progressSummary(): array
    {
        $progress = StudentProgress::query()
            ->where('student_id', auth()->id())
            ->where('project_id', $this->project->id)
            ->whereNull('phase_id')
            ->first();

        $snapshot = PerformanceSnapshot::query()
            ->where('student_id', auth()->id())
            ->where('project_id', $this->project->id)
            ->latest('snapshot_date')
            ->first();

        $levelKey = $snapshot?->metrics['qualitative_level_key'] ?? null;

        return [
            'pct' => $progress?->progress_pct ?? 0,
            'level' => $levelKey !== null ? RubricLevel::where('key', $levelKey)->first() : null,
        ];
    }

    /**
     * Chat de equipo (Hito de chat privado): solo se ofrece si el propio
     * estudiante es integrante de ALGÚN ProjectTeam de este proyecto -- un
     * proyecto puede tener varios equipos (uno por grupo, o varios dentro
     * del mismo grupo), y un estudiante nunca pertenece a más de uno en el
     * mismo proyecto en la práctica actual (ProjectTeamsRelationManager no
     * impide duplicar la membresía, pero first() basta: si algún día
     * ocurriera, se muestra el primero, nunca un error).
     */
    public function myTeam(): ?ProjectTeam
    {
        return ProjectTeam::query()
            ->where('project_id', $this->project->id)
            ->whereHas('users', fn ($query) => $query->whereKey(auth()->id()))
            ->first();
    }

    public function render()
    {
        return view('livewire.student.project-show', [
            'phases' => $this->phases(),
            'progressSummary' => $this->progressSummary(),
            'myTeam' => $this->myTeam(),
        ]);
    }
}
