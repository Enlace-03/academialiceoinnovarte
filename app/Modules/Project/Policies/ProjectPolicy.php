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
 *
 * view() tiene una tercera rama para student (Hito 3b-1): un estudiante no
 * tiene projects.view.all ni .own (son permisos de personal, nunca de un rol
 * fijo — ver permissions-conventions) — ve el proyecto si su grado pertenece
 * al ciclo del proyecto, vía el mismo User::canAccessProject() que ya usan
 * ForumThreadPolicy/ForumPostPolicy.
 *
 * viewAsStaff() existe aparte de view() porque ForumThreadPolicy/
 * ForumPostPolicy/SubmissionPolicy usan "¿puede ver el proyecto?" como atajo
 * de "es personal, así que ve TODO — incluido lo oculto/ajeno" antes de
 * aplicar su propio chequeo de is_hidden/ownership para estudiantes. Si esas
 * Policies usaran view() completo (con la rama student ya incluida), un
 * estudiante colaría por ese atajo y se saltaría el chequeo de is_hidden —
 * bug real detectado por ForumThreadPolicyTest/ForumPostPolicyTest al agregar
 * la rama student aquí. viewAsStaff() es deliberadamente solo las dos ramas
 * de personal, para que ese atajo siga significando "es personal", nunca
 * "cualquiera con acceso de lectura".
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
        return $this->viewAsStaff($user, $project)
            || ($user->hasRole('student') && $user->canAccessProject($project));
    }

    public function viewAsStaff(User $user, Project $project): bool
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
