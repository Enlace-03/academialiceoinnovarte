<?php

declare(strict_types=1);

namespace App\Modules\Community\Actions;

use App\Models\User;
use App\Modules\Community\Models\ChatMessage;
use App\Modules\Institution\Models\Group;

final class SendChatMessageAction
{
    public function execute(Group $group, User $author, string $content): ChatMessage
    {
        return ChatMessage::create([
            'group_id' => $group->id,
            'user_id' => $author->id,
            'content' => $content,
        ]);
    }
}
