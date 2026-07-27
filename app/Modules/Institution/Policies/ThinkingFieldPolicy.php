<?php

declare(strict_types=1);

namespace App\Modules\Institution\Policies;

use App\Models\User;
use App\Modules\Institution\Models\ThinkingField;

// Campos de pensamiento: estructura institucional estable, CRUD simple bajo un único permiso.
class ThinkingFieldPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('institution.thinking-fields.manage');
    }

    public function view(User $user, ThinkingField $thinkingField): bool
    {
        return $user->hasPermissionTo('institution.thinking-fields.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('institution.thinking-fields.manage');
    }

    public function update(User $user, ThinkingField $thinkingField): bool
    {
        return $user->hasPermissionTo('institution.thinking-fields.manage');
    }

    public function delete(User $user, ThinkingField $thinkingField): bool
    {
        return $user->hasPermissionTo('institution.thinking-fields.manage');
    }
}
