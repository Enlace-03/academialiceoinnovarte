<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserGrant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Asigna permisos a un usuario aplicando el TECHO DE DELEGACIÓN estricto:
// el otorgante solo puede conceder permisos que él mismo posee.
//
// Esta validación es del lado del servidor: NUNCA confiar en que la UI ya filtró.
// La UI (Filament) también filtra las casillas, pero esta Action es la última línea.
final class AssignPermissionsAction
{
    /**
     * @param  User   $granter            Quien otorga.
     * @param  User   $target             Quien recibe.
     * @param  array  $permissions        Claves de permiso a otorgar.
     * @param  array  $scopedPermissions  Mapa permiso => scope (para permisos con alcance).
     */
    public function execute(
        User $granter,
        User $target,
        array $permissions,
        array $scopedPermissions = [],
    ): void {
        // 1. El otorgante debe tener el permiso meta de delegar.
        abort_unless($granter->hasPermissionTo('users.grant'), 403,
            'No tienes permiso para otorgar permisos a otros usuarios.');

        // 2. TECHO: cada permiso solicitado debe estar en el conjunto del otorgante.
        //    Excepción: admin (con comodín) puede otorgar cualquier permiso del catálogo.
        $granterPermissions = $this->effectivePermissionKeys($granter);

        foreach ($permissions as $permission) {
            $canGrant = $granter->hasRole('admin')
                || in_array($permission, $granterPermissions, true);

            if (! $canGrant) {
                throw ValidationException::withMessages([
                    'permissions' => "No puedes otorgar el permiso «{$permission}» porque tú no lo tienes.",
                ]);
            }
        }

        // 3. Para permisos con alcance, validar que el otorgante no exceda SU propio alcance.
        //    Ej: si el otorgante solo puede matricular en el grupo 3, no puede conceder "todos".
        foreach ($scopedPermissions as $permission => $scope) {
            $this->assertScopeWithinGranterScope($granter, $permission, $scope);
        }

        DB::transaction(function () use ($granter, $target, $permissions, $scopedPermissions) {
            // 4. Sincronizar permisos base (spatie) — para los sin alcance.
            $plainPermissions = array_values(array_diff(
                $permissions,
                array_keys($scopedPermissions)
            ));
            $target->syncPermissions($plainPermissions);

            // 5. Registrar los permisos con alcance en user_grants + darlos también en spatie.
            UserGrant::where('user_id', $target->id)->delete();

            foreach ($scopedPermissions as $permission => $scope) {
                $target->givePermissionTo($permission);
                UserGrant::create([
                    'user_id'    => $target->id,
                    'granted_by' => $granter->id,
                    'permission' => $permission,
                    'scope'      => $scope,
                ]);
            }
        });
    }

    /** Permisos efectivos del otorgante (directos + por rol). */
    private function effectivePermissionKeys(User $granter): array
    {
        return $granter->getAllPermissions()->pluck('name')->all();
    }

    /**
     * Impide que el otorgante conceda un alcance mayor al suyo.
     * Regla:
     *  - Si el otorgante tiene el permiso con scope "all" (o es admin/rector con alcance total),
     *    puede conceder cualquier scope.
     *  - Si el otorgante solo lo tiene acotado a ciertos grupos, solo puede conceder
     *    un subconjunto de esos grupos.
     */
    private function assertScopeWithinGranterScope(User $granter, string $permission, array $scope): void
    {
        // admin y rector con el permiso sin restricción → alcance total.
        if ($granter->hasRole('admin') || $granter->hasRole('rector')) {
            return;
        }

        $granterGrant = UserGrant::where('user_id', $granter->id)
            ->where('permission', $permission)
            ->first();

        // Si el otorgante no tiene registro de scope pero sí el permiso base, se asume
        // que su alcance NO es total: no puede delegar este permiso con alcance.
        if (! $granterGrant || ! is_array($granterGrant->scope)) {
            throw ValidationException::withMessages([
                'permissions' => "No puedes definir el alcance de «{$permission}»: tu propio alcance no lo permite.",
            ]);
        }

        $granterScope = $granterGrant->scope;

        // Si el otorgante tiene alcance total, todo vale.
        if (($granterScope['type'] ?? null) === 'all') {
            return;
        }

        // Si el otorgante está acotado a grupos, el nuevo scope debe ser subconjunto.
        if (($granterScope['type'] ?? null) === 'groups') {
            $allowed = $granterScope['group_ids'] ?? [];
            $requested = $scope['type'] === 'all'
                ? ['*'] // pidió "todos" pero el otorgante no puede darlo
                : ($scope['group_ids'] ?? []);

            $excess = array_diff($requested, $allowed);
            if (! empty($excess)) {
                throw ValidationException::withMessages([
                    'permissions' => "El alcance solicitado para «{$permission}» excede tu propio alcance.",
                ]);
            }
        }
    }
}
