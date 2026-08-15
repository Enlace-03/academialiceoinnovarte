<?php

declare(strict_types=1);

namespace App\Modules\Community\Actions;

use App\Models\User;
use App\Modules\Community\Models\ChatMessage;
use App\Modules\Community\Models\ForumPost;
use App\Modules\Community\Models\ForumThread;
use App\Modules\Community\Models\PrivateChatMessage;

/**
 * Compartida entre los cuatro tipos de contenido moderable: las cuatro
 * tablas tienen exactamente las mismas tres columnas de moderación
 * (is_hidden/hidden_at/hidden_by_user_id), así que una sola Action basta —
 * quién puede invocarla para cada tipo lo decide la Policy correspondiente,
 * no esta Action. PrivateChatMessage se agregó sin tocar el criterio de
 * los otros tres (chat privado estudiante-docente).
 */
final class HideCommunityContentAction
{
    public function execute(ForumThread|ForumPost|ChatMessage|PrivateChatMessage $content, User $moderator): void
    {
        $content->update([
            'is_hidden' => true,
            'hidden_at' => now(),
            'hidden_by_user_id' => $moderator->id,
        ]);
    }
}
