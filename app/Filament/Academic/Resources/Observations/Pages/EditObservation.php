<?php

namespace App\Filament\Academic\Resources\Observations\Pages;

use App\Filament\Academic\Resources\Observations\ObservationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditObservation extends EditRecord
{
    protected static string $resource = ObservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
