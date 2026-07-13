<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleLevelSeeder extends Seeder
{
    protected array $levels = [
        'rector'      => 80,
        'coordinator' => 60,
        'secretary'   => 40,
        'teacher'     => 30,
        'parent'      => 10,
        'student'     => 5,
    ];

    public function run(): void
    {
        // Create the super_admin role if it doesn't exist
        Role::firstOrCreate(
            ['name' => 'super_admin', 'guard_name' => 'web'],
            ['level' => 100]
        );

        // Update levels of existing roles
        foreach ($this->levels as $roleName => $level) {
            $role = Role::where('name', $roleName)->first();

            if (! $role) {
                $this->command?->warn("Rol '{$roleName}' no existe, se omite.");
                continue;
            }

            $role->update(['level' => $level]);
        }
    }
}