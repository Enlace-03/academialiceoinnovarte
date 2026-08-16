<?php

declare(strict_types=1);

namespace App\Livewire\Student;

use App\Modules\Project\Actions\ResolvePendingEvidencesForStudentAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Calendario de mes con las entregas pendientes del propio estudiante (hito
 * de dashboard enriquecido): sin librería nueva (confirmado en la auditoría
 * previa que package.json no trae ninguna), construido con Blade/Livewire
 * puro + Carbon para la grilla.
 *
 * Reutiliza ResolvePendingEvidencesForStudentAction con $from/$to del mes
 * visible (no el default "desde hoy" que usan PortalHome/MyProjects) --
 * necesario para que un end_date ya pasado DENTRO del mes visible siga
 * apareciendo (la Action ya excluye lo resuelto, así que todo lo que
 * devuelve con due_date pasado es, por definición, "vencido": todavía sin
 * entregar). Sin filtro por proyecto ni eventos institucionales (fuera de
 * alcance de este hito, ver TODO.md).
 */
#[Layout('layouts.portal')]
class MyCalendar extends Component
{
    public int $year;

    public int $month;

    public ?string $selectedDate = null;

    public function mount(): void
    {
        $this->year = (int) now()->year;
        $this->month = (int) now()->month;
    }

    public function previousMonth(): void
    {
        $cursor = Carbon::create($this->year, $this->month, 1)->subMonth();
        $this->year = $cursor->year;
        $this->month = $cursor->month;
        $this->selectedDate = null;
    }

    public function nextMonth(): void
    {
        $cursor = Carbon::create($this->year, $this->month, 1)->addMonth();
        $this->year = $cursor->year;
        $this->month = $cursor->month;
        $this->selectedDate = null;
    }

    public function selectDate(string $date): void
    {
        $this->selectedDate = $this->selectedDate === $date ? null : $date;
    }

    /**
     * @return Collection<string, Collection<int, array>> indexado por 'Y-m-d'
     */
    public function pendingByDate(): Collection
    {
        $from = Carbon::create($this->year, $this->month, 1)->startOfMonth();
        $to = $from->copy()->endOfMonth();

        return app(ResolvePendingEvidencesForStudentAction::class)
            ->execute(auth()->user(), $from, $to)
            ->groupBy(fn (array $entry) => $entry['due_date']->toDateString());
    }

    public function render()
    {
        $pendingByDate = $this->pendingByDate();

        return view('livewire.student.my-calendar', [
            'monthStart' => Carbon::create($this->year, $this->month, 1),
            'pendingByDate' => $pendingByDate,
            'selectedEntries' => $this->selectedDate !== null
                ? ($pendingByDate->get($this->selectedDate) ?? collect())
                : null,
        ]);
    }
}
