<?php

declare(strict_types=1);

namespace App\Modules\Community\Actions;

use App\Models\User;
use App\Modules\Community\Models\ChatMessage;
use App\Modules\Community\Models\ForumPost;
use App\Modules\Community\Models\ForumThread;
use App\Modules\Community\Models\PrivateChatMessage;

/**
 * user_id/created_by de estas cuatro tablas usan restrictOnDelete() (ver
 * migración 2027_01_01_000450) -- sin este chequeo previo, UserResource
 * dejaría pasar el DELETE hasta la BD y el estudiante/docente vería la
 * excepción SQL cruda de Filament en vez de un mensaje entendible.
 */
final class UserHasCommunityContentAction
{
    public function execute(User $user): bool
    {
        return ChatMessage::where('user_id', $user->id)->exists()
            || PrivateChatMessage::where('user_id', $user->id)->exists()
            || ForumPost::where('user_id', $user->id)->exists()
            || ForumThread::where('created_by', $user->id)->exists();
    }
}
