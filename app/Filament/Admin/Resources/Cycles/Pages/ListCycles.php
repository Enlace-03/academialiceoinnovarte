<?php

namespace App\Filament\Admin\Resources\Cycles\Pages;

use App\Filament\Admin\Resources\Cycles\CycleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCycles extends ListRecords
{
    protected static string $resource = CycleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
