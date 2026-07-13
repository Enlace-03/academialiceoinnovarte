<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Defense in depth: even though the Filament form already filters the
 * visible options, this rule validates again on the server that the
 * submitted IDs are within what the authenticated user can delegate.
 * Without this, someone could tamper with the request and send
 * disallowed IDs.
 */
class WithinDelegationCeiling implements ValidationRule
{
    /**
     * @param  array<int, int|string>  $allowedIds  Allowed IDs for this user and this field
     */
    public function __construct(protected array $allowedIds) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $submitted = is_array($value) ? $value : [$value];

        $notAllowed = array_diff($submitted, $this->allowedIds);

        if (! empty($notAllowed)) {
            $fail('Seleccionaste uno o más valores que superan tu techo de delegación.');
        }
    }
}
