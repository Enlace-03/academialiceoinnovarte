<?php

declare(strict_types=1);

namespace App\Modules\Project\Policies;

use App\Models\User;
use App\Modules\Project\Models\Project;

/**
 * "own" se define por Project::created_by_user_id. phases.manage y
 * resources.manage NO son puertas independientes: solo aplican sobre un
 * proyecto si el usuario ya tiene update() sobre ese proyecto puntual
 * (el suyo, o cualquiera si tiene el permiso .all). Un docente con
 * phases.manage global no puede tocar las fases de un proyecto ajeno.
 */
class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('projects.view.all')
            || $user->hasPermissionTo('projects.view.own');
    }

    public function view(User $user, Project $project): bool
    {
        return $user->hasPermissionTo('projects.view.all')
            || ($user->hasPermissionTo('projects.view.own') && $project->created_by_user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('projects.create');
    }

    public function update(User $user, Project $project): bool
    {
        return $user->hasPermissionTo('projects.update.all')
            || ($user->hasPermissionTo('projects.update.own') && $project->created_by_user_id === $user->id);
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }

    public function managePhases(User $user, Project $project): bool
    {
        return $user->hasPermissionTo('phases.manage') && $this->update($user, $project);
    }

    public function manageResources(User $user, Project $project): bool
    {
        return $user->hasPermissionTo('resources.manage') && $this->update($user, $project);
    }
}
