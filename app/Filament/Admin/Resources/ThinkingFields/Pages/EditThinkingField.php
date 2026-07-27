<?php

namespace App\Filament\Admin\Resources\ThinkingFields\Pages;

use App\Filament\Admin\Resources\ThinkingFields\ThinkingFieldResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditThinkingField extends EditRecord
{
    protected static string $resource = ThinkingFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
