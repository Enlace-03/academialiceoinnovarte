<?php

declare(strict_types=1);

namespace App\Modules\Community\Events;

use App\Models\User;
use App\Modules\Community\Models\ForumPost;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado por ToggleForumPostLikeAction cuando el resultado es "unlike"
 * (false), sin cambiar su comportamiento existente.
 */
final class ForumPostUnliked
{
    use SerializesModels;

    public function __construct(
        public readonly ForumPost $post,
        public readonly User $user,
    ) {}
}
