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
 *
 * maxWidth (hito de layout ancho, dos columnas): max-w-7xl -- grid
 * grid-cols-1 lg:grid-cols-3 en la vista (calendario 2/3, actividades 1/3,
 * mismo patrón 2:1 que ya usa ProjectShow), más ancho que el max-w-3xl de
 * una sola columna de antes (default de layouts.portal) para que el
 * calendario en sí no encoja al
 * agregarle la columna lateral.
 *
 * $selectedDate === null (por defecto, o al cambiar de mes/deseleccionar):
 * la columna lateral muestra TODAS las entregas pendientes del mes visible,
 * no solo las de un día -- ver monthEntries()/render(). Con un día
 * seleccionado, se filtra a ese día. Lista vacía en cualquiera de los dos
 * casos oculta la columna lateral por completo (sin mensaje "sin entregas"
 * -- decisión explícita de este hito, distinta del mensaje que sí muestra
 * MyProjects para su propio bloque "Próxima entrega").
 */
#[Layout('layouts.portal', ['maxWidth' => 'max-w-7xl'])]
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
     * @return Collection<int, array> todas las entregas pendientes del mes
     *   visible, ordenadas por fecha -- fuente única para pendingByDate()
     *   (agrupada, para las marcas del grid) y para el default de
     *   displayedEntries() (sin agrupar, para la columna lateral).
     */
    public function monthEntries(): Collection
    {
        $from = Carbon::create($this->year, $this->month, 1)->startOfMonth();
        $to = $from->copy()->endOfMonth();

        return app(ResolvePendingEvidencesForStudentAction::class)
            ->execute(auth()->user(), $from, $to);
    }

    /**
     * @return Collection<string, Collection<int, array>> indexado por 'Y-m-d'
     */
    public function pendingByDate(): Collection
    {
        return $this->monthEntries()->groupBy(fn (array $entry) => $entry['due_date']->toDateString());
    }

    public function render()
    {
        $monthEntries = $this->monthEntries();
        $pendingByDate = $monthEntries->groupBy(fn (array $entry) => $entry['due_date']->toDateString());

        $displayedEntries = $this->selectedDate !== null
            ? ($pendingByDate->get($this->selectedDate) ?? collect())
            : $monthEntries;

        return view('livewire.student.my-calendar', [
            'monthStart' => Carbon::create($this->year, $this->month, 1),
            'pendingByDate' => $pendingByDate,
            'displayedEntries' => $displayedEntries,
        ]);
    }
}
