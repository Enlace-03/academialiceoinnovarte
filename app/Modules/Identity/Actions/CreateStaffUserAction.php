<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Events\UserCreated;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Crea un usuario y le asigna permisos, respetando el techo de delegación:
// el creador solo puede otorgar permisos que él mismo posee.
//
// Uso desde Filament o Livewire:
//   app(CreateStaffUserAction::class)->execute($creator, $data, $selectedPermissions);
final class CreateStaffUserAction
{
    public function __construct(
        private readonly AssignPermissionsAction $assignPermissions,
    ) {}

    /**
     * @param  User    $creator             Quien está creando el usuario (el otorgante).
     * @param  array   $data                name, email, password, institution_id...
     * @param  array   $permissions         Lista de claves de permiso a otorgar.
     * @param  array   $scopedPermissions   Mapa permiso => scope, para permisos con alcance.
     *                                       Ej: ['students.create.scoped' => ['type' => 'groups', 'group_ids' => [3,5]]]
     */
    public function execute(
        User $creator,
        array $data,
        array $permissions = [],
        array $scopedPermissions = [],
    ): User {
        // 1. El creador debe poder crear usuarios.
        abort_unless($creator->hasPermissionTo('users.create'), 403,
            'No tienes permiso para crear usuarios.');

        return DB::transaction(function () use ($creator, $data, $permissions, $scopedPermissions) {
            // 2. Crear el usuario.
            $user = User::create([
                'uuid'           => (string) Str::uuid(),
                'name'           => $data['name'],
                'email'          => $data['email'],
                'password'       => bcrypt($data['password']),
                'institution_id' => $data['institution_id'] ?? $creator->institution_id,
            ]);

            // 3. Asignar permisos respetando el techo (la Action valida cada uno).
            $this->assignPermissions->execute($creator, $user, $permissions, $scopedPermissions);

            // 4. Evento de dominio (onboarding, notificación de bienvenida, etc.).
            event(new UserCreated($user, $creator));

            return $user;
        });
    }
}
