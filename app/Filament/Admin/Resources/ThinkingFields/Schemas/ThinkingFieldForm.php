<?php

namespace App\Filament\Admin\Resources\ThinkingFields\Schemas;

use App\Modules\Institution\Models\Institution;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ThinkingFieldForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Hidden::make('institution_id')
                ->default(fn () => Institution::query()->value('id')),

            TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(fn (string $state, callable $set) => $set('slug', Str::slug($state)))
                ->maxLength(100),

            TextInput::make('slug')
                ->label('Slug')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(100),

            TextInput::make('order')
                ->label('Orden')
                ->numeric()
                ->minValue(1)
                ->maxValue(4)
                ->required(),

            Textarea::make('purpose')
                ->label('Propósito')
                ->columnSpanFull(),
        ]);
    }
}
