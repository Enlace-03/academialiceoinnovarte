<?php

namespace App\Filament\Admin\Resources\Groups\Schemas;

use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\InstitutionSetting;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class GroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('cycle_id')
                ->label('Ciclo')
                ->options(fn () => Cycle::query()
                    ->orderBy('order')
                    ->pluck('name', 'id'))
                ->required()
                ->searchable(),

            TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength(255),

            TextInput::make('year')
                ->label('Año lectivo')
                ->numeric()
                ->default(fn () => InstitutionSetting::get(
                    'current_academic_year',
                    config('school.current_academic_year'),
                ))
                ->required(),
        ]);
    }
}
