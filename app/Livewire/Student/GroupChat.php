<?php

declare(strict_types=1);

namespace App\Livewire\Student;

use App\Modules\Community\Actions\SendChatMessageAction;
use App\Modules\Community\Models\ChatMessage;
use App\Modules\Institution\Models\Group;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Sin group_id en la URL a propósito: siempre es el grupo del propio
 * estudiante (auth()->user()->group), así que no hay superficie de URL para
 * cruzar hacia el chat de otro grupo. Sin grado/grupo asignado, no hay chat
 * (fail-closed, mismo criterio que User::canAccessProject).
 *
 * wire:poll en la vista simula "en vivo" sin broadcasting (el proyecto no
 * tiene Redis/Echo — ver CLAUDE.md).
 *
 * Defensa en profundidad (mismo patrón que ForumThreadShow::mount() con el
 * chequeo de {project}/{thread}): mount() valida hasRole('student') por sí
 * mismo, ANTES de confiar en auth()->user()->group. Sin este chequeo, el
 * componente depende de dos capas externas para no exponer el chat de un
 * grupo real a personal: (1) el middleware role:student de la ruta, y (2)
 * que group_id nunca quede no-nulo en una cuenta staff. Esa segunda capa es
 * GroupRequiresStudentRole (app/Rules/GroupRequiresStudentRole.php) — una
 * regla de VALIDACIÓN atada solo al formulario UserForm de Filament, no un
 * constraint de BD ni una regla del modelo. Si algún día alguien escribe
 * group_id por otro camino (tinker, un seeder, una Action futura) sin pasar
 * por ese formulario, o si la ruta pierde role:student en un refactor,
 * ChatMessagePolicy::create() ya autoriza a cualquier staff sin mirar el
 * group_id (ver docblock de esa Policy) — este chequeo es lo único que no
 * depende de ninguna de esas dos capas. Ver TODO.md #18.
 */
#[Layout('layouts.portal')]
class GroupChat extends Component
{
    public ?Group $group = null;

    #[Validate('required|string|max:2000')]
    public string $content = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole('student'), 403);

        $this->group = auth()->user()->group;

        if ($this->group !== null) {
            $this->authorize('create', [ChatMessage::class, $this->group]);
        }
    }

    /**
     * No se llama messages(): ese nombre está reservado por Livewire para
     * los mensajes de error de validación (HandlesValidation::getMessages())
     * — usarlo aquí rompe la validación con un TypeError críptico
     * (array_merge espera array, recibe esta Collection).
     */
    public function chatMessages(): Collection
    {
        if ($this->group === null) {
            return new Collection();
        }

        return $this->group->chatMessages()
            ->where('is_hidden', false)
            ->with('user')
            ->oldest()
            ->get();
    }

    public function send(): void
    {
        abort_if($this->group === null, 403);

        $this->authorize('create', [ChatMessage::class, $this->group]);

        $this->validate();

        app(SendChatMessageAction::class)->execute($this->group, auth()->user(), $this->content);

        $this->content = '';
    }

    public function render()
    {
        return view('livewire.student.group-chat', [
            'messages' => $this->chatMessages(),
        ]);
    }
}
