<?php

namespace App\Filament\Admin\Resources\ThinkingFields\Pages;

use App\Filament\Admin\Resources\ThinkingFields\ThinkingFieldResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListThinkingFields extends ListRecords
{
    protected static string $resource = ThinkingFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
