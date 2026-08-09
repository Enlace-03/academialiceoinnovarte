<?php

declare(strict_types=1);

namespace App\Modules\Community\Events;

use App\Modules\Community\Models\ChatMessage;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado por SendChatMessageAction, sin cambiar su comportamiento
 * existente. chat_messages no tiene project_id (TODO.md #16, decisión ya
 * confirmada) -- el Listener resuelve a qué proyecto(s) afecta a partir
 * del ciclo del grupo, no de este evento.
 */
final class ChatMessageSent
{
    use SerializesModels;

    public function __construct(public readonly ChatMessage $message) {}
}
