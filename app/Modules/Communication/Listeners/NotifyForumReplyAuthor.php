<?php

declare(strict_types=1);

namespace App\Modules\Communication\Listeners;

use App\Modules\Communication\Notifications\ForumReplyReceived;
use App\Modules\Community\Events\ForumPostCreated;

/**
 * Correo + plataforma solo para respuestas directas (parent_post_id no
 * nulo) -- nunca por actividad general del hilo, y nunca si el autor del
 * post padre es quien responde (decisión confirmada del Hito 5a).
 */
final class NotifyForumReplyAuthor
{
    public function handle(ForumPostCreated $event): void
    {
        $post = $event->post;

        if ($post->parent_post_id === null) {
            return;
        }

        $parentPost = $post->parentPost;

        if ($parentPost === null || $parentPost->user_id === $post->user_id) {
            return;
        }

        $parentPost->user->notify(new ForumReplyReceived($post));
    }
}
