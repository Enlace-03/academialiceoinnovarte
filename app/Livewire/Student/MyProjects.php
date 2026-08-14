<?php

declare(strict_types=1);

namespace App\Livewire\Student;

use App\Modules\Project\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Lista los proyectos del ciclo del estudiante (User::canAccessProject): un
 * proyecto es del ciclo, no de un grupo puntual, así que todos los grados de
 * ese ciclo lo comparten. Sin grado asignado, lista vacía (fail-closed, igual
 * que canAccessProject).
 *
 * status='published' explícito acá (hito borrador/publicado): este query NO
 * pasa por ProjectPolicy en absoluto (a diferencia de ProjectShow, que sí
 * autoriza vía Policy) -- sin este filtro, un proyecto en borrador seguiría
 * apareciendo en la lista aunque el clic para entrar fallara con 403. Mismo
 * criterio que ProjectResource::getEloquentQuery() ya usa del lado staff:
 * reforzar a nivel de listado lo que la Policy exige a nivel de registro.
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
            ->orderByDesc('year')
            ->orderByDesc('semester')
            ->orderBy('title')
            ->get();
    }

    public function render()
    {
        return view('livewire.student.my-projects', [
            'projects' => $this->projects(),
        ]);
    }
}
