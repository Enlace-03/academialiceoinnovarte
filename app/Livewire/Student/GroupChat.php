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
 */
#[Layout('layouts.portal')]
class GroupChat extends Component
{
    public ?Group $group = null;

    #[Validate('required|string|max:2000')]
    public string $content = '';

    public function mount(): void
    {
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
