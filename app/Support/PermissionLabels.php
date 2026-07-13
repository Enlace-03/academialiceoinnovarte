<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Shared display labels for roles and permissions, in Spanish, for the
 * admin panel UI. Used by both the UserResource form and table so labels
 * stay consistent in one place instead of being duplicated per file.
 */
final class PermissionLabels
{
    public static function role(string $name): string
    {
        return match ($name) {
            'super_admin' => 'Super Administrador',
            'rector' => 'Rector',
            'coordinator' => 'Coordinador',
            'secretary' => 'Secretaría',
            'teacher' => 'Docente',
            'student' => 'Estudiante',
            'parent' => 'Acudiente',
            default => Str::headline($name),
        };
    }

    /**
     * Converts 'users.view' into 'Gestión de usuarios — Ver usuarios' using
     * config/permissions.php as the source of the human-readable labels.
     */
    public static function permission(string $name): string
    {
        foreach (config('permissions.catalog', []) as $groupLabel => $permissions) {
            if (array_key_exists($name, $permissions)) {
                return "{$groupLabel} — {$permissions[$name]}";
            }
        }

        return $name;
    }
}
