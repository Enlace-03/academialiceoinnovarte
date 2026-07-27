<?php

namespace App\Filament\Admin\Resources\Cycles;

use App\Filament\Admin\Resources\Cycles\Pages\CreateCycle;
use App\Filament\Admin\Resources\Cycles\Pages\EditCycle;
use App\Filament\Admin\Resources\Cycles\Pages\ListCycles;
use App\Filament\Admin\Resources\Cycles\Schemas\CycleForm;
use App\Filament\Admin\Resources\Cycles\Tables\CyclesTable;
use App\Modules\Institution\Models\Cycle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class CycleResource extends Resource
{
    protected static ?string $model = Cycle::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string | UnitEnum | null $navigationGroup = 'Institución';

    protected static ?string $navigationLabel = 'Ciclos';

    protected static ?string $modelLabel = 'Ciclo';

    protected static ?string $pluralModelLabel = 'Ciclos';

    public static function form(Schema $schema): Schema
    {
        return CycleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CyclesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCycles::route('/'),
            'create' => CreateCycle::route('/create'),
            'edit' => EditCycle::route('/{record}/edit'),
        ];
    }
}
