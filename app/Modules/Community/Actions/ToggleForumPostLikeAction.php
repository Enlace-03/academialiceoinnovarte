<?php

declare(strict_types=1);

namespace App\Modules\Community\Actions;

use App\Models\User;
use App\Modules\Community\Events\ForumPostLiked;
use App\Modules\Community\Events\ForumPostUnliked;
use App\Modules\Community\Models\ForumPost;

final class ToggleForumPostLikeAction
{
    /**
     * @return bool  true si el post quedó con like del usuario, false si se quitó
     */
    public function execute(ForumPost $post, User $user): bool
    {
        if ($post->likes()->where('user_id', $user->id)->exists()) {
            $post->likes()->detach($user->id);

            event(new ForumPostUnliked($post, $user));

            return false;
        }

        $post->likes()->attach($user->id);

        event(new ForumPostLiked($post, $user));

        return true;
    }
}
