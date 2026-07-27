<?php

namespace App\Filament\Admin\Resources\Cycles\Pages;

use App\Filament\Admin\Resources\Cycles\CycleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCycle extends EditRecord
{
    protected static string $resource = CycleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
