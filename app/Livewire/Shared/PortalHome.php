<?php

declare(strict_types=1);

namespace App\Livewire\Shared;

use App\Models\User;
use App\Modules\Identity\Actions\RemoveStudentPhotoAction;
use App\Modules\Identity\Actions\UploadStudentPhotoAction;
use App\Modules\Project\Actions\ResolvePendingEvidencesForStudentAction;
use App\Modules\Tracking\Actions\AggregateThinkingFieldProgressAction;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Placeholder post-login del panel fuera de Filament (Hito 3b-0), hoy
 * relevante solo para acudiente -- desde el hito de dashboard enriquecido,
 * un student que aterriza acá se redirige de inmediato a MyProjects
 * (mismo patrón defensivo que ya usa isStaff() abajo), porque MyProjects
 * pasó a ser su verdadero home. Para acudiente, desde el Hito 5a, sigue
 * siendo su dashboard mínimo real (lista de hijos + evidencias pendientes
 * con fecha límite próxima). Desde el Hito 4b, se agrega el avance agregado
 * por campo de pensamiento de cada hijo (AggregateThinkingFieldProgressAction,
 * mismo cálculo y mismo componente <x-thinking-field-progress> que el
 * portal de estudiante) -- primera pieza de progreso real del dashboard del
 * acudiente, anticipada desde el Hito 5a. Sigue sin nivel cualitativo
 * agregado (diferido al hito de Boletines, ver TODO.md) ni barra por
 * proyecto individual -- el dashboard completo del acudiente sigue siendo
 * un hito de diseño aparte.
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
 * (confirmado contra el comportamiento real de Filament, no supuesto). El
 * redirect a student.projects.index no tiene ese mismo riesgo por una razón
 * más simple: MyProjects no redirige a nada, es una pantalla real.
 */
#[Layout('layouts.portal')]
class PortalHome extends Component
{
    use WithFileUploads;

    /**
     * Indexado por student_id: cada hijo tiene su propio selector de archivo
     * independiente en la misma pantalla (lista de hijos, no un detalle por
     * hijo) -- ver uploadPhoto()/portal-home.blade.php.
     *
     * @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile>
     */
    public array $photoUploads = [];

    public function mount(): void
    {
        if (auth()->user()->isStaff()) {
            $this->redirect(Filament::getPanel('academic')->getUrl());

            return;
        }

        if (auth()->user()->hasRole('student')) {
            $this->redirect(route('student.projects.index'));
        }
    }

    public function childrenDashboard(): Collection
    {
        if (! auth()->user()->hasRole('parent')) {
            return new Collection();
        }

        return auth()->user()->children()->with('schoolGrade.cycle')->orderBy('name')->get()
            ->map(fn (User $child) => [
                'child' => $child,
                'canManagePhoto' => ($child->schoolGrade?->cycle?->order ?? null) !== null && $child->schoolGrade->cycle->order <= 2,
                'pending' => app(ResolvePendingEvidencesForStudentAction::class)->execute($child),
                'thinkingFieldProgress' => app(AggregateThinkingFieldProgressAction::class)->execute($child),
            ]);
    }

    /**
     * La ValidationException que puede tirar UploadStudentPhotoAction (foto
     * demasiado grande para procesar con la memoria disponible, subida
     * bloqueada) llega con su propia clave interna ('photo'), no con la
     * 'photoUploads.{id}' que este formulario usa -- si se dejara escapar
     * tal cual, Livewire la agrega igual al error bag, pero bajo una clave
     * que ningún @error de la vista escucha: el acudiente vería el botón
     * "Guardar foto" sin reaccionar, sin ningún mensaje visible. Se
     * re-lanza remapeada a la clave correcta para que sí aparezca.
     */
    public function uploadPhoto(int $childId): void
    {
        $child = User::findOrFail($childId);
        abort_unless(auth()->user()->isGuardianOf($child), 404);

        $this->validate([
            "photoUploads.{$childId}" => 'required|image|max:8192',
        ]);

        try {
            app(UploadStudentPhotoAction::class)->execute(auth()->user(), $child, $this->photoUploads[$childId]);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages([
                "photoUploads.{$childId}" => collect($e->errors())->flatten()->first(),
            ]);
        }

        unset($this->photoUploads[$childId]);
    }

    public function removePhoto(int $childId): void
    {
        $child = User::findOrFail($childId);
        abort_unless(auth()->user()->isGuardianOf($child), 404);

        app(RemoveStudentPhotoAction::class)->execute(auth()->user(), $child);
    }

    public function render()
    {
        return view('livewire.shared.portal-home', [
            'childrenDashboard' => $this->childrenDashboard(),
        ]);
    }
}
