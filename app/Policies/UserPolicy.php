<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * NOTE: this policy assumes 'users.view', 'users.create', 'users.update'
     * and 'users.delete' permissions defined in your PermissionSeeder.
     * Adjust the names if you already use a different convention.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $user, User $target): bool
    {
        return $user->can('users.view');
    }

    /**
     * users.create: vía completa, crea cualquier rol (sujeto igual al techo
     * de delegación de HasDelegationCeiling::assignableRoles() para CUÁLES
     * roles). students.create: vía adicional, más angosta -- autoriza
     * llegar a esta misma pantalla (la única de creación de usuarios en el
     * proyecto), pero HasDelegationCeiling::assignableRoles() es quien de
     * verdad restringe el desplegable de rol a student/parent para quien
     * entra solo por esta vía (ver UserForm). No reemplaza el chequeo de
     * users.create, se suma a él.
     */
    public function create(User $user): bool
    {
        return $user->can('users.create') || $user->can('students.create');
    }

    /**
     * users.update: vía completa, sujeta al techo de delegación normal
     * (HasDelegationCeiling::canManageUser()). students.create: vía
     * adicional, más angosta (Hito de permisos, corrección #3) -- deja
     * editar ÚNICAMENTE usuarios con rol student o parent, sin importar el
     * resto de su propio conjunto de permisos. Existe para que quien solo
     * tiene el permiso atómico de estudiante/acudiente pueda llegar a
     * GuardiansRelationManager (vive en la página de edición, no en la de
     * creación) y vincular un acudiente -- sin poder editar ningún otro
     * tipo de usuario. No reutiliza canManageUser() a propósito: ese método
     * compara conjuntos de permisos (ya deja pasar a student/parent porque
     * no cargan ningún permiso del catálogo, así que sería redundante) --
     * acá se necesita el chequeo directo del ROL del objetivo, no una
     * comparación de permisos.
     */
    public function update(User $user, User $target): bool
    {
        if ($user->can('users.update') && $user->canManageUser($target)) {
            return true;
        }

        return $user->can('students.create') && $target->hasAnyRole(['student', 'parent']);
    }

    public function delete(User $user, User $target): bool
    {
        return $user->can('users.delete')
            && $user->canManageUser($target)
            && ! $user->is($target);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('users.delete');
    }

    /**
     * Foto de perfil de estudiante (ciclos 1-2 únicamente, decisión
     * confirmada -- ciclos 3-4 quedan sin esta opción hasta el futuro hito
     * de autogestión del estudiante). Gobierna tanto subir como quitar la
     * propia foto: mismo criterio de elegibilidad en los dos casos, un
     * acudiente nunca podría haber subido una foto para un hijo de ciclo 3-4
     * en primer lugar.
     */
    public function uploadPhoto(User $user, User $student): bool
    {
        return $this->isEligibleGuardianForPhoto($user, $student);
    }

    public function removeOwnPhoto(User $user, User $student): bool
    {
        return $this->isEligibleGuardianForPhoto($user, $student);
    }

    /**
     * Personal autorizado a eliminar/bloquear/desbloquear la foto de
     * CUALQUIER estudiante -- students.photo.moderate es un permiso propio,
     * deliberadamente no plegado dentro de ningún users.* existente (decisión
     * confirmada), asignado únicamente a coordinator y rector.
     */
    public function moderatePhoto(User $user, User $student): bool
    {
        return $user->hasPermissionTo('students.photo.moderate');
    }

    /**
     * Quién puede ver la foto de un estudiante: personal (cualquier rol
     * staff) o alguno de sus propios acudientes -- mismo criterio de
     * aislamiento que el resto de la app (nunca por adivinar la URL).
     */
    public function viewPhoto(User $user, User $student): bool
    {
        return $user->isStaff() || $user->isGuardianOf($student);
    }

    private function isEligibleGuardianForPhoto(User $user, User $student): bool
    {
        $cycleOrder = $student->schoolGrade?->cycle?->order;

        return $cycleOrder !== null
            && $cycleOrder <= 2
            && $user->isGuardianOf($student);
    }
}
