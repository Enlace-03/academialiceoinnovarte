<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Integrity rule: a role categorized as "identity" (student, parent) in
 * config('permissions.role_categories') must be the user's only role — it
 * cannot be combined with a staff role nor with another identity role.
 * Staff roles keep combining freely among themselves; this rule only fires
 * when the submitted set contains at least one identity role and more than
 * one role in total.
 */
class ExclusiveIdentityRoleRule implements ValidationRule
{
    /**
     * @param  array<int, int|string>  $identityRoleIds  IDs of roles categorized as "identity"
     */
    public function __construct(protected array $identityRoleIds) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $submitted = is_array($value) ? $value : [$value];

        if (count($submitted) < 2) {
            return;
        }

        $hasIdentityRole = count(array_intersect($submitted, $this->identityRoleIds)) > 0;

        if ($hasIdentityRole) {
            $fail('Un usuario con rol de Estudiante o Acudiente no puede tener ningún otro rol asignado.');
        }
    }
}
