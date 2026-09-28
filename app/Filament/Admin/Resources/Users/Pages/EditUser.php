<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\User;
use App\Modules\Community\Actions\UserHasCommunityContentAction;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\QueryException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    private const CANNOT_DELETE_MESSAGE = 'No se puede eliminar: este usuario tiene mensajes o publicaciones. Desactívalo en su lugar.';

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
                // el chequeo previo evita el intento; el catch de using()
                // cubre la carrera (contenido creado entre el chequeo y el
                // DELETE) en vez de dejar la excepción SQL sin manejar.
                ->before(function (DeleteAction $action): void {
                    if (app(UserHasCommunityContentAction::class)->execute($this->record)) {
                        Notification::make()
                            ->warning()
                            ->title(self::CANNOT_DELETE_MESSAGE)
                            ->send();

                        $action->halt();
                    }
                })
                ->using(function (User $record): bool {
                    try {
                        return (bool) $record->delete();
                    } catch (QueryException $e) {
                        if (! in_array($e->getCode(), ['23000', 23000], true)) {
                            throw $e;
                        }

                        return false;
                    }
                })
                ->failureNotificationTitle(self::CANNOT_DELETE_MESSAGE),
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
