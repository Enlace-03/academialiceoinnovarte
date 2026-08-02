<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

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
                ->visible(fn () => $this->record->id !== auth()->id()),
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
