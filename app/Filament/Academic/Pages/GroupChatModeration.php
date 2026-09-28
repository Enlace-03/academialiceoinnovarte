<?php

declare(strict_types=1);

namespace App\Filament\Academic\Pages;

use App\Modules\Community\Actions\HideCommunityContentAction;
use App\Modules\Community\Models\ChatMessage;
use App\Modules\Institution\Models\Group;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Moderación del chat de grupo (mismo patrón que PrivateChats, chat.moderate
 * en vez de private_chats.view.all): un solo listado por grupo, sin un
 * componente Livewire compartido de por medio -- a diferencia de
 * PrivateChatPanel, no existe ningún componente de chat de grupo apto para
 * un moderador (App\Livewire\Student\GroupChat exige hasRole('student') en
 * su propio mount(), así que no se puede reusar tal cual aquí). La página
 * misma lista los mensajes y expone hide(), autorizado por
 * ChatMessagePolicy::hide() a través de HideCommunityContentAction (la misma
 * Action que usan foro y chat privado).
 */
class GroupChatModeration extends Page
{
    protected static string | UnitEnum | null $navigationGroup = 'Comunidad';

    protected static ?string $navigationLabel = 'Chat de grupo';

    protected static ?string $title = 'Chat de grupo — moderación';

    protected static ?string $slug = 'chat-de-grupo';

    protected string $view = 'filament.academic.pages.group-chat-moderation';

    public ?int $groupId = null;

    public static function canAccess(): bool
    {
        return Auth::user()?->hasPermissionTo('chat.moderate') ?? false;
    }

    /**
     * @return Collection<int, Group>
     */
    public function groups(): Collection
    {
        return Group::query()->with('cycle')->get()
            ->sortBy(fn (Group $group) => [$group->cycle?->order, $group->name])
            ->values();
    }

    /**
     * @return Collection<int, ChatMessage>
     */
    public function messages(): Collection
    {
        if ($this->groupId === null) {
            return collect();
        }

        return ChatMessage::query()
            ->where('group_id', $this->groupId)
            ->with('user')
            ->oldest()
            ->get();
    }

    public function hide(int $messageId): void
    {
        $message = ChatMessage::findOrFail($messageId);

        $this->authorize('hide', $message);

        app(HideCommunityContentAction::class)->execute($message, auth()->user());
    }
}
