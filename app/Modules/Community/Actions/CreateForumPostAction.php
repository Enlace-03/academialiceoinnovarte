<?php

declare(strict_types=1);

namespace App\Modules\Community\Actions;

use App\Models\User;
use App\Modules\Community\Models\ForumPost;
use App\Modules\Community\Models\ForumThread;
use Illuminate\Validation\ValidationException;

/**
 * Respuestas de un solo nivel: si parent_post_id apunta a un post que ya es
 * en sí mismo una respuesta (tiene su propio parent_post_id), o a un post
 * oculto, se rechaza — la FK por sí sola no impide ninguna de las dos cosas.
 */
final class CreateForumPostAction
{
    /**
     * @param  array{content: string, parent_post_id?: int|null}  $data
     */
    public function execute(ForumThread $thread, User $author, array $data): ForumPost
    {
        $parentPostId = $data['parent_post_id'] ?? null;

        if ($parentPostId !== null) {
            $parentPost = ForumPost::findOrFail($parentPostId);

            if ($parentPost->parent_post_id !== null) {
                throw ValidationException::withMessages([
                    'parent_post_id' => 'Solo se permite un nivel de respuesta.',
                ]);
            }

            if ($parentPost->is_hidden) {
                throw ValidationException::withMessages([
                    'parent_post_id' => 'No se puede responder a un post oculto.',
                ]);
            }
        }

        return ForumPost::create([
            'forum_thread_id' => $thread->id,
            'parent_post_id' => $parentPostId,
            'user_id' => $author->id,
            'content' => $data['content'],
        ]);
    }
}
