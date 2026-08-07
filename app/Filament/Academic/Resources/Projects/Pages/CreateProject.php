<?php

namespace App\Filament\Academic\Resources\Projects\Pages;

use App\Filament\Academic\Resources\Projects\ProjectResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProject extends CreateRecord
{
    protected static string $resource = ProjectResource::class;

    /**
     * created_by_user_id nunca viene del formulario (evita que se falsee
     * desde el cliente): siempre es quien está autenticado al crear.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_user_id'] = auth()->id();

        return $data;
    }
}
