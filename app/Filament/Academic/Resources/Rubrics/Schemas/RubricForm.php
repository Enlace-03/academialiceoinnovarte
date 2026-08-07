<?php

namespace App\Filament\Academic\Resources\Rubrics\Schemas;

use App\Modules\Institution\Models\Institution;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RubricForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Hidden::make('institution_id')
                ->default(fn () => Institution::query()->value('id')),

            TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->columnSpanFull(),

            Textarea::make('description')
                ->label('Descripción')
                ->rows(2)
                ->columnSpanFull(),

            Repeater::make('criteria')
                ->relationship()
                ->label('Criterios')
                ->schema([
                    TextInput::make('name')
                        ->label('Criterio')
                        ->required()
                        ->columnSpanFull(),

                    // Los 4 niveles son fijos (catálogo rubric_levels): aquí solo
                    // se escribe la descripción de cada nivel para este criterio,
                    // nunca el nombre/orden del nivel — eso viene del catálogo.
                    Textarea::make('level_descriptions.inicio')
                        ->label('Inicio')
                        ->rows(2)
                        ->required(),

                    Textarea::make('level_descriptions.en_proceso')
                        ->label('En proceso')
                        ->rows(2)
                        ->required(),

                    Textarea::make('level_descriptions.logro_esperado')
                        ->label('Logro esperado')
                        ->rows(2)
                        ->required(),

                    Textarea::make('level_descriptions.logro_destacado')
                        ->label('Logro destacado')
                        ->rows(2)
                        ->required(),
                ])
                ->columns(2)
                ->collapsible()
                ->reorderable('position')
                ->addActionLabel('Agregar criterio')
                ->columnSpanFull(),
        ]);
    }
}
