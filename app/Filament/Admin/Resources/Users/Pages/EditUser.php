<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Users\UserResource;
use App\Modules\Community\Actions\UserHasCommunityContentAction;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Mismo mecanismo y misma razón que CreateUser::authorizeResourceAccess()
     * (Hito de permisos, corrección #3): la puerta genérica de Filament para
     * TODO el Resource (`CanAuthorizeResourceAccess`, default
     * `canViewAny()` = users.view) se dispara ANTES de que el record de la
     * ruta esté disponible -- no puede saber todavía si el usuario objetivo
     * es un student/parent editable por la vía angosta. El recorte real por
     * registro puntual ya lo hace `canEdit($record)` dentro de
     * `InteractsWithRecord::mount()` (ver `authorizeAccess()` de esa
     * trait), que SÍ tiene el record y llama a `UserPolicy::update()` --
     * esa es la autorización que de verdad importa. Esta puerta genérica
     * solo necesita dejar pasar a quien tiene AL MENOS una vía real hacia
     * este Resource; se reusa `canCreate()` como ese proxy, igual que hace
     * CreateUser -- alguien con solo students.create pasa aquí, y
     * `canEdit($record)` decide después si ESTE registro puntual es
     * realmente suyo para editar.
     */
    public static function authorizeResourceAccess(): void
    {
        abort_unless(
            static::getResource()::canViewAny() || static::getResource()::canCreate(),
            403,
        );
    }

    /**
     * @return array<Action|ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        // Los botones se mueven fuera del <form>, así que necesitan el atributo
        // HTML `form` para poder seguir enviándolo (ver getFormId() en Action).
        $formActions = array_map(
            fn (Action|ActionGroup $action): Action|ActionGroup => ($action instanceof Action && $action->canSubmitForm())
                ? $action->formId('form')
                : $action,
            parent::getFormActions(),
        );

        return [
            ...$formActions,
            DeleteAction::make()
                ->visible(fn () => $this->record->id !== auth()->id())
                // chat_messages/private_chat_messages/forum_posts/forum_threads
                // usan restrictOnDelete() (migración 2027_01_01_000450) --
                // sin este chequeo, el DELETE llegaría hasta la BD y el
                // usuario vería la excepción SQL cruda en vez de un mensaje
                // entendible.
                ->before(function (DeleteAction $action): void {
                    if (app(UserHasCommunityContentAction::class)->execute($this->record)) {
                        Notification::make()
                            ->warning()
                            ->title('Este usuario tiene mensajes; desactívalo en vez de borrarlo.')
                            ->send();

                        $action->halt();
                    }
                }),
        ];
    }

    /**
     * @return array<Action|ActionGroup>
     */
    protected function getFormActions(): array
    {
        return [];
    }
}
