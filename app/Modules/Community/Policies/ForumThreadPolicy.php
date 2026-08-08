<?php

declare(strict_types=1);

namespace App\Modules\Community\Policies;

use App\Models\User;
use App\Modules\Community\Models\ForumThread;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Policies\ProjectPolicy;

/**
 * Sin permiso propio de moderación (el huérfano forums.moderate se retiró):
 * hide() reutiliza ProjectPolicy::update() sobre el proyecto del hilo — un
 * docente modera hilos de sus propios proyectos, igual que edita sus fases;
 * rector/coordinator moderan cualquiera vía projects.update.all.
 *
 * Un estudiante ve/crea si su grado pertenece al ciclo del proyecto
 * (User::canAccessProject) — nunca puede ocultar nada, porque hide() exige
 * projects.update.*, permiso que un rol fijo (student) nunca tiene.
 */
class ForumThreadPolicy
{
    public function view(User $user, ForumThread $thread): bool
    {
        if (app(ProjectPolicy::class)->view($user, $thread->project)) {
            return true;
        }

        return ! $thread->is_hidden && $user->canAccessProject($thread->project);
    }

    public function create(User $user, Project $project): bool
    {
        if (app(ProjectPolicy::class)->view($user, $project)) {
            return true;
        }

        return $user->canAccessProject($project);
    }

    public function hide(User $user, ForumThread $thread): bool
    {
        return app(ProjectPolicy::class)->update($user, $thread->project);
    }
}
