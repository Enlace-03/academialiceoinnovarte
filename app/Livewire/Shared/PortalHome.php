<?php

declare(strict_types=1);

namespace App\Livewire\Shared;

use App\Models\User;
use App\Modules\Assessment\Models\Submission;
use App\Modules\Project\Models\StudentPhaseSchedule;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Placeholder post-login del panel fuera de Filament (Hito 3b-0) para
 * estudiante; para acudiente, desde el Hito 5a, es su dashboard mínimo real
 * (lista de hijos + evidencias pendientes con fecha límite próxima) -- sin
 * barra de avance ni nivel cualitativo, a propósito (ver TODO.md, el
 * dashboard completo del acudiente queda como hito de diseño aparte).
 */
#[Layout('layouts.portal')]
class PortalHome extends Component
{
    public function childrenPendingEvidences(): Collection
    {
        if (! auth()->user()->hasRole('parent')) {
            return new Collection();
        }

        return auth()->user()->children()->orderBy('name')->get()
            ->map(fn (User $child) => [
                'child' => $child,
                'pending' => $this->pendingEvidencesFor($child),
            ]);
    }

    private function pendingEvidencesFor(User $child): Collection
    {
        $schedules = StudentPhaseSchedule::query()
            ->where('student_id', $child->id)
            ->where('end_date', '>=', now()->toDateString())
            ->with('phase.expectedEvidences')
            ->orderBy('end_date')
            ->get();

        $resolvedEvidenceIds = Submission::query()
            ->where('student_id', $child->id)
            ->whereIn('status', ['submitted', 'evaluated'])
            ->pluck('expected_evidence_id');

        return $schedules
            ->flatMap(fn (StudentPhaseSchedule $schedule) => $schedule->phase->expectedEvidences
                ->reject(fn ($evidence) => $resolvedEvidenceIds->contains($evidence->id))
                ->map(fn ($evidence) => [
                    'phase_name' => $schedule->phase->name,
                    'description' => $evidence->description,
                    'due_date' => $schedule->end_date,
                ]))
            ->sortBy('due_date')
            ->values();
    }

    public function render()
    {
        return view('livewire.shared.portal-home', [
            'childrenPendingEvidences' => $this->childrenPendingEvidences(),
        ]);
    }
}
