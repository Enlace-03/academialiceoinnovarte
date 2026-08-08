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
