<?php

declare(strict_types=1);

namespace App\Livewire\Student;

use App\Modules\Project\Actions\ResolvePendingEvidencesForStudentAction;
use App\Modules\Project\Models\Project;
use App\Modules\Tracking\Actions\AggregateThinkingFieldProgressAction;
use App\Modules\Tracking\Models\StudentProgress;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Home real del estudiante (desde el hito de dashboard enriquecido, es el
 * destino directo de PortalHome -- ver ese componente): lista los proyectos
 * del ciclo del estudiante (User::canAccessProject) un proyecto es del
 * ciclo, no de un grupo puntual, así que todos los grados de ese ciclo lo
 * comparten. Sin grado asignado, lista vacía (fail-closed, igual que
 * canAccessProject).
 *
 * status='published' explícito acá (hito borrador/publicado): este query NO
 * pasa por ProjectPolicy en absoluto (a diferencia de ProjectShow, que sí
 * autoriza vía Policy) -- sin este filtro, un proyecto en borrador seguiría
 * apareciendo en la lista aunque el clic para entrar fallara con 403. Mismo
 * criterio que ProjectResource::getEloquentQuery() ya usa del lado staff:
 * reforzar a nivel de listado lo que la Policy exige a nivel de registro.
 *
 * "Próxima entrega" (hito de dashboard enriquecido) reutiliza
 * ResolvePendingEvidencesForStudentAction -- mismo cruce StudentPhaseSchedule
 * + Submission que ya usaba PortalHome del lado del acudiente, acá aplicado
 * a auth()->user() con ventana de 7 días (estilo Classroom) en vez de sin
 * límite. El progreso por tarjeta lee StudentProgress ya precalculado por
 * Tracking (fila phase_id=null, "del proyecto completo"), mismo dato que ya
 * usa ProjectShow::progressSummary() -- sin cálculo nuevo acá.
 */
#[Layout('layouts.portal')]
class MyProjects extends Component
{
    public function projects(): Collection
    {
        $cycleId = auth()->user()->schoolGrade?->cycle_id;

        if ($cycleId === null) {
            return new Collection();
        }

        return Project::query()
            ->where('cycle_id', $cycleId)
            ->where('status', 'published')
            ->with('createdBy')
            ->orderByDesc('year')
            ->orderByDesc('semester')
            ->orderBy('title')
            ->get();
    }

    /**
     * Indexado por project_id -- una fila StudentProgress por proyecto
     * (phase_id null es la fila "del proyecto completo").
     */
    public function progressByProject(Collection $projects): Collection
    {
        return StudentProgress::query()
            ->where('student_id', auth()->id())
            ->whereIn('project_id', $projects->pluck('id'))
            ->whereNull('phase_id')
            ->get()
            ->keyBy('project_id');
    }

    public function pendingEvidences(): \Illuminate\Support\Collection
    {
        return app(ResolvePendingEvidencesForStudentAction::class)
            ->execute(auth()->user(), now(), now()->addDays(7));
    }

    public function render()
    {
        $projects = $this->projects();

        return view('livewire.student.my-projects', [
            'projects' => $projects,
            'progressByProject' => $this->progressByProject($projects),
            'pendingEvidences' => $this->pendingEvidences(),
            'thinkingFieldProgress' => app(AggregateThinkingFieldProgressAction::class)->execute(auth()->user()),
        ]);
    }
}
