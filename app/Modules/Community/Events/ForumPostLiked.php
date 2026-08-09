<?php

declare(strict_types=1);

namespace App\Modules\Community\Events;

use App\Models\User;
use App\Modules\Community\Models\ForumPost;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado por ToggleForumPostLikeAction cuando el resultado es "like"
 * (true), sin cambiar su comportamiento existente. $user es quien da el
 * like, no el autor del post -- es quien participa.
 */
final class ForumPostLiked
{
    use SerializesModels;

    public function __construct(
        public readonly ForumPost $post,
        public readonly User $user,
    ) {}
}
