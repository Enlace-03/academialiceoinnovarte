<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// Crea todos los permisos del catálogo y los roles fijos (student, parent).
// Los "presets" de personal (admin, rector, coordinador, secretario, teacher)
// también se crean como roles de conveniencia que precargan permisos, pero
// la autoridad real de cada usuario son sus permisos directos (marcables).
final class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Crear todos los permisos del catálogo.
        $catalog = config('permissions.catalog');
        $allPermissionKeys = [];

        foreach ($catalog as $group => $permissions) {
            foreach ($permissions as $key => $label) {
                Permission::firstOrCreate(['name' => $key, 'guard_name' => 'web']);
                $allPermissionKeys[] = $key;
            }
        }

        // 2. Roles fijos uniformes: student y parent (sin permisos del catálogo).
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);

        // 3. Roles-preset del personal, precargando permisos.
        $presets = config('permissions.presets');

        foreach ($presets as $roleName => $permissionKeys) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            if ($permissionKeys === '*') {
                $role->syncPermissions($allPermissionKeys); // admin: todo
            } else {
                $role->syncPermissions($permissionKeys);
            }
        }

        // Nota: al crear un usuario en Filament, se le puede asignar un rol-preset
        // (que precarga sus permisos) Y/O marcar permisos individuales encima,
        // siempre dentro del techo de delegación de quien lo crea.
    }
}
