<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Filament pone DOS puertas por delante de esta página: la genérica de
     * CreateRecord (ya correcta, `canCreate()` -> UserPolicy::create(), que
     * ahora acepta también students.create) Y esta, la de "acceso al
     * Resource completo" (`CanAuthorizeResourceAccess`), cuyo default es
     * `UserResource::canAccess()` = `canViewAny()` = `users.view`. Sin este
     * override, alguien con SOLO students.create (sin users.view) rebotaba
     * acá con un 403 genérico antes de siquiera llegar a la puerta de
     * `canCreate()` -- confirmado en vivo durante el Hito de permisos,
     * corrección #2. Se sobrescribe únicamente para esta página: List/Edit
     * siguen exigiendo users.view/users.update sin cambios, `canAccess()`
     * del Resource no se toca (evita ensanchar sin querer la visibilidad
     * del listado completo de usuarios a alguien que solo debería poder
     * crear).
     */
    public static function authorizeResourceAccess(): void
    {
        abort_unless(static::getResource()::canCreate(), 403);

        if ($parentResource = static::getParentResource()) {
            abort_unless($parentResource::canAccess(), 403);
        }
    }

    /**
     * @return array<Action|ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        // Los botones se mueven fuera del <form>, así que necesitan el atributo
        // HTML `form` para poder seguir enviándolo (ver getFormId() en Action).
        return array_map(
            fn (Action|ActionGroup $action): Action|ActionGroup => ($action instanceof Action && $action->canSubmitForm())
                ? $action->formId('form')
                : $action,
            parent::getFormActions(),
        );
    }

    /**
     * @return array<Action|ActionGroup>
     */
    protected function getFormActions(): array
    {
        return [];
    }
}
