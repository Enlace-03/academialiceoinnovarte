<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Sin valor por defecto a propósito: un default conocido dejaría al
        // super admin con una contraseña adivinable si alguien olvida definir
        // la variable en un entorno real. Se valida ANTES de sembrar nada para
        // no dejar la base a medias.
        $superAdminPassword = env('SEED_SUPER_ADMIN_PASSWORD');

        if (! is_string($superAdminPassword) || $superAdminPassword === '') {
            throw new RuntimeException('Define SEED_SUPER_ADMIN_PASSWORD antes de correr este seeder');
        }

        $this->call(RolePermissionSeeder::class);
        $this->call(RoleLevelSeeder::class);
        $this->call(InstitutionSeeder::class);
        $this->call(RubricLevelSeeder::class);

        $diego = User::firstOrCreate(
            ['email' => 'diego@admin.edu.co'],
            [
                'name' => 'Diego',
                'password' => $superAdminPassword,
                'email_verified_at' => now(),
            ]
        );

        $diego->assignRole('super_admin');
    }
}
