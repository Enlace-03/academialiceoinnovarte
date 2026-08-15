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

    /**
     * Rama estudiante: combinada, no reemplazada, con el borrador/publicado
     * (hito posterior a la nota de arriba) -- canAccessProject() exige mismo
     * ciclo, status === 'published' exige que el docente ya lo haya hecho
     * visible. Un proyecto publicado de otro ciclo sigue sin ser visible, y
     * uno del ciclo correcto pero en borrador tampoco. Personal (viewAsStaff)
     * no se toca -- sigue viendo cualquier estado, incluidos borradores
     * propios o ajenos según su alcance own/all ya existente.
     */
    public function view(User $user, Project $project): bool
    {
        return $this->viewAsStaff($user, $project)
            || ($user->hasRole('student')
                && $user->canAccessProject($project)
                && $project->status === 'published');
    }

    public function viewAsStaff(User $user, Project $project): bool
    {
        return $user->hasPermissionTo('projects.view.all')
            || ($user->hasPermissionTo('projects.view.own') && $project->created_by_user_id === $user->id);
    }

    /**
     * Rama acudiente (drill-down del dashboard de acudiente): mismo criterio
     * de ciclo+publicado que la rama student de view(), pero evaluado contra
     * el HIJO objetivo, nunca contra el usuario autenticado -- el acudiente
     * mismo no tiene school_grade/cycle propio. Método aparte, no una rama
     * más de view(), por la misma razón que viewAsStaff ya es aparte: view()
     * solo recibe (User $user, Project $project), sin forma de pasarle el
     * hijo objetivo. Se llama explícitamente vía
     * $this->authorize('viewAsGuardian', [$project, $child]) -- nunca se
     * confía solo en esta Policy: cada componente del drill-down re-verifica
     * $user->isGuardianOf($child) a mano también (defensa en profundidad).
     */
    public function viewAsGuardian(User $user, Project $project, User $child): bool
    {
        return $user->hasRole('parent')
            && $user->isGuardianOf($child)
            && $child->canAccessProject($project)
            && $project->status === 'published';
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
