<?php

declare(strict_types=1);

namespace App\Modules\Institution\Policies;

use App\Models\User;
use App\Modules\Institution\Models\SchoolGrade;

// Grados escolares: nunca se borran (is_active los saca de los selectores de
// matrícula sin afectar a estudiantes ya matriculados), por eso no hay delete().
class SchoolGradePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('institution.school-grades.manage');
    }

    public function view(User $user, SchoolGrade $schoolGrade): bool
    {
        return $user->hasPermissionTo('institution.school-grades.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('institution.school-grades.manage');
    }

    public function update(User $user, SchoolGrade $schoolGrade): bool
    {
        return $user->hasPermissionTo('institution.school-grades.manage');
    }

    public function delete(User $user, SchoolGrade $schoolGrade): bool
    {
        return false;
    }
}
