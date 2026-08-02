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
