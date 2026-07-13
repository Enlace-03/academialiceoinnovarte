<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);
        $this->call(RoleLevelSeeder::class);
        $this->call(InstitutionSeeder::class);

        $diego = User::firstOrCreate(
            ['email' => 'diego@admin.edu.co'],
            [
                'name' => 'Diego',
                'password' => env('SEED_SUPER_ADMIN_PASSWORD', 'changeme123'),
                'email_verified_at' => now(),
            ]
        );

        $diego->assignRole('super_admin');
    }
}