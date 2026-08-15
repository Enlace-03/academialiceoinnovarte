<?php

declare(strict_types=1);

namespace App\Livewire\Shared;

use App\Models\User;
use App\Modules\Assessment\Models\Submission;
use App\Modules\Project\Models\StudentPhaseSchedule;
use App\Modules\Tracking\Actions\AggregateThinkingFieldProgressAction;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Placeholder post-login del panel fuera de Filament (Hito 3b-0) para
 * estudiante; para acudiente, desde el Hito 5a, es su dashboard mínimo real
 * (lista de hijos + evidencias pendientes con fecha límite próxima). Desde
 * el Hito 4b, se agrega el avance agregado por campo de pensamiento de cada
 * hijo (AggregateThinkingFieldProgressAction, mismo cálculo y mismo
 * componente <x-thinking-field-progress> que el portal de estudiante) --
 * primera pieza de progreso real del dashboard del acudiente, anticipada
 * desde el Hito 5a. Sigue sin nivel cualitativo agregado (diferido al hito
 * de Boletines, ver TODO.md) ni barra por proyecto individual -- el
 * dashboard completo del acudiente sigue siendo un hito de diseño aparte.
 *
 * mount(): personal (staff) que aterriza acá -- por login compartido en /login
 * en vez de /academia/login, un bookmark viejo, o sesión ya abierta -- se
 * redirige al panel académico de inmediato, no hay nada útil para su rol en
 * este placeholder. Chequeo en el propio destino (no solo en el momento del
 * login), mismo criterio de defensa en profundidad que ya usa
 * GroupChat::mount() en este proyecto (TODO.md #18) -- cubre cualquier forma
 * en que el staff termine en / , no solo el submit del formulario de login.
 *
 * Sin riesgo de bucle: isStaff() acá es EXACTAMENTE la misma condición que
 * User::canAccessPanel() exige para el panel 'academic' (ambas llaman al
 * mismo método). Si algún día divergieran, el peor caso sigue sin ser un
 * bucle -- el middleware de autenticación de Filament responde con un 403
 * cuando canAccessPanel() es false, nunca con un redirect de vuelta a '/'
 * (confirmado contra el comportamiento real de Filament, no supuesto).
 */
#[Layout('layouts.portal')]
class PortalHome extends Component
{
    public function mount(): void
    {
        if (auth()->user()->isStaff()) {
            $this->redirect(Filament::getPanel('academic')->getUrl());
        }
    }

    public function childrenDashboard(): Collection
    {
        if (! auth()->user()->hasRole('parent')) {
            return new Collection();
        }

        return auth()->user()->children()->orderBy('name')->get()
            ->map(fn (User $child) => [
                'child' => $child,
                'pending' => $this->pendingEvidencesFor($child),
                'thinkingFieldProgress' => app(AggregateThinkingFieldProgressAction::class)->execute($child),
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
            'childrenDashboard' => $this->childrenDashboard(),
        ]);
    }
}
