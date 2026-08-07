<?php

namespace App\Filament\Academic\Resources\Observations\Pages;

use App\Filament\Academic\Resources\Observations\ObservationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateObservation extends CreateRecord
{
    protected static string $resource = ObservationResource::class;

    /**
     * teacher_id nunca viene del formulario: siempre es quien está
     * autenticado al crear (igual patrón que created_by_user_id en Project).
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['teacher_id'] = auth()->id();

        return $data;
    }
}
