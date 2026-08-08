<?php

declare(strict_types=1);

namespace App\Modules\Community\Policies;

use App\Models\User;
use App\Modules\Community\Models\ForumPost;
use App\Modules\Community\Models\ForumThread;
use App\Modules\Project\Policies\ProjectPolicy;

/**
 * Likes: el conteo (likes()->count()) es público para cualquiera que pueda
 * ver el post; el LISTADO de quién dio like (viewLikers) es solo para
 * personal — nunca para estudiantes, ni siquiera de su propio proyecto.
 *
 * El atajo "es personal, ve todo" usa ProjectPolicy::viewAsStaff(), NO
 * view() completo — view() ya incluye la rama student (Hito 3b-1); usar
 * view() aquí dejaría a un estudiante colarse por el atajo y ver posts/hilos
 * ocultos, o incluso la lista de likers (viewLikers), saltándose los
 * chequeos de abajo.
 */
class ForumPostPolicy
{
    public function view(User $user, ForumPost $post): bool
    {
        if (app(ProjectPolicy::class)->viewAsStaff($user, $post->thread->project)) {
            return true;
        }

        return ! $post->is_hidden
            && ! $post->thread->is_hidden
            && $user->canAccessProject($post->thread->project);
    }

    public function create(User $user, ForumThread $thread): bool
    {
        if (app(ProjectPolicy::class)->viewAsStaff($user, $thread->project)) {
            return true;
        }

        return ! $thread->is_hidden && $user->canAccessProject($thread->project);
    }

    public function hide(User $user, ForumPost $post): bool
    {
        return app(ProjectPolicy::class)->update($user, $post->thread->project);
    }

    public function like(User $user, ForumPost $post): bool
    {
        return $this->view($user, $post);
    }

    public function viewLikers(User $user, ForumPost $post): bool
    {
        return app(ProjectPolicy::class)->viewAsStaff($user, $post->thread->project);
    }
}
