<?php

declare(strict_types=1);

namespace App\Livewire\Shared;

use App\Models\User;
use App\Modules\Community\Actions\HideCommunityContentAction;
use App\Modules\Community\Actions\SendPrivateChatMessageAction;
use App\Modules\Community\Models\PrivateChatMessage;
use App\Modules\Community\Models\PrivateChatThread;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTeam;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Un solo componente reutilizado en los tres contextos del pedido:
 * portal de estudiante (individual y de equipo, embebido en ProjectShow),
 * panel académico del docente (embebido en un modal de Filament vía
 * Schemas\Components\Livewire dentro de PrivateChatThreadsRelationManager)
 * y la página de solo lectura institucional -- la audiencia real (puede
 * enviar, puede ocultar) la decide siempre PrivateChatThreadPolicy, nunca
 * una bandera de "modo" propia del componente.
 *
 * El hilo puede no existir todavía: se recibe el CONTEXTO (proyecto+tipo+
 * estudiante o equipo), nunca un PrivateChatThread ya resuelto -- se
 * busca sin crear en thread()/messages(), y solo se crea (firstOrCreate,
 * dentro de SendPrivateChatMessageAction) en el momento real de send().
 */
class PrivateChatPanel extends Component
{
    public Project $project;

    public string $type;

    public ?User $student = null;

    public ?ProjectTeam $team = null;

    #[Validate('required|string|max:2000')]
    public string $content = '';

    public function mount(Project $project, string $type, ?User $student = null, ?ProjectTeam $team = null): void
    {
        abort_unless(in_array($type, PrivateChatThread::TYPES, true), 404);

        $this->project = $project;
        $this->type = $type;
        $this->student = $student;
        $this->team = $team;

        $this->authorize('viewContext', [PrivateChatThread::class, $project, $type, $student, $team]);
    }

    public function send(): void
    {
        $this->authorize('create', [PrivateChatThread::class, $this->project, $this->type, $this->student, $this->team]);

        $this->validate();

        app(SendPrivateChatMessageAction::class)->execute(
            $this->project, $this->type, $this->student, $this->team, auth()->user(), $this->content,
        );

        $this->content = '';
    }

    public function hide(int $messageId): void
    {
        $message = PrivateChatMessage::with('thread')->findOrFail($messageId);

        $this->authorize('moderate', $message->thread);

        app(HideCommunityContentAction::class)->execute($message, auth()->user());
    }

    public function canSend(): bool
    {
        return auth()->user()->can('create', [PrivateChatThread::class, $this->project, $this->type, $this->student, $this->team]);
    }

    public function canModerate(): bool
    {
        return auth()->user()->hasPermissionTo('private_chats.moderate');
    }

    public function heading(): string
    {
        if ($this->type === 'team') {
            return $this->team !== null ? "Chat de equipo — {$this->team->name}" : 'Chat de equipo';
        }

        if (auth()->user()->hasRole('student')) {
            return 'Chat con tu docente';
        }

        return $this->student !== null ? "Chat individual — {$this->student->name}" : 'Chat individual';
    }

    /**
     * Sin crear el hilo -- solo lo busca. No existir todavía es un estado
     * normal (nadie ha escrito el primer mensaje), no un error.
     */
    private function thread(): ?PrivateChatThread
    {
        return PrivateChatThread::query()
            ->where('project_id', $this->project->id)
            ->where('type', $this->type)
            ->where('student_id', $this->type === 'individual' ? $this->student?->id : null)
            ->where('team_id', $this->type === 'team' ? $this->team?->id : null)
            ->first();
    }

    /**
     * NO se llama messages(): ese nombre está reservado por Livewire para
     * los mensajes de error de validación (HandlesValidation::getMessages())
     * -- usarlo aquí rompe la validación (mismo bug real ya documentado en
     * GroupChat::chatMessages(), encontrado de nuevo en la verificación de
     * este componente).
     *
     * @return Collection<int, PrivateChatMessage>
     */
    private function threadMessages(): Collection
    {
        $thread = $this->thread();

        if ($thread === null) {
            return new Collection();
        }

        return $thread->messages()
            ->when(! $this->canModerate(), fn ($query) => $query->where('is_hidden', false))
            ->with(['user', 'hiddenBy'])
            ->oldest()
            ->get();
    }

    public function render()
    {
        return view('livewire.shared.private-chat-panel', [
            'messages' => $this->threadMessages(),
            'canSend' => $this->canSend(),
            'canModerate' => $this->canModerate(),
        ]);
    }
}
