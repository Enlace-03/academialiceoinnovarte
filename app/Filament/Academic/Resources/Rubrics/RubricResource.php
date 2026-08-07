<?php

namespace App\Filament\Academic\Resources\Rubrics;

use App\Filament\Academic\Resources\Rubrics\Pages\CreateRubric;
use App\Filament\Academic\Resources\Rubrics\Pages\EditRubric;
use App\Filament\Academic\Resources\Rubrics\Pages\ListRubrics;
use App\Filament\Academic\Resources\Rubrics\Schemas\RubricForm;
use App\Filament\Academic\Resources\Rubrics\Tables\RubricsTable;
use App\Modules\Assessment\Models\Rubric;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class RubricResource extends Resource
{
    protected static ?string $model = Rubric::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string | UnitEnum | null $navigationGroup = 'ABP';

    protected static ?string $navigationLabel = 'Rúbricas';

    protected static ?string $modelLabel = 'Rúbrica';

    protected static ?string $pluralModelLabel = 'Rúbricas';

    public static function form(Schema $schema): Schema
    {
        return RubricForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RubricsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRubrics::route('/'),
            'create' => CreateRubric::route('/create'),
            'edit' => EditRubric::route('/{record}/edit'),
        ];
    }
}
