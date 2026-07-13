<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Integrity rule (application layer, not DB): only a user with the
 * student role should carry a group_id. The role lives in Spatie's pivot
 * table, so it can't be enforced with a DB constraint — this validates the
 * combination on every save, same spirit as WithinDelegationCeiling.
 */
class GroupRequiresStudentRole implements ValidationRule
{
    public function __construct(protected bool $hasStudentRole) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value !== null && ! $this->hasStudentRole) {
            $fail('Solo se puede asignar un grupo a usuarios con rol Estudiante.');
        }
    }
}
