<?php

namespace App\Filament\Admin\Resources\Cycles\Schemas;

use App\Modules\Institution\Models\Institution;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CycleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Hidden::make('institution_id')
                ->default(fn () => Institution::query()->value('id')),

            TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength(60),

            TextInput::make('order')
                ->label('Orden')
                ->numeric()
                ->minValue(1)
                ->maxValue(4)
                ->required(),

            Textarea::make('description')
                ->label('Descripción')
                ->helperText('Habilidad central del ciclo.')
                ->columnSpanFull(),
        ]);
    }
}
