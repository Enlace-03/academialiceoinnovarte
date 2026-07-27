<?php

namespace App\Filament\Admin\Resources\SchoolGrades;

use App\Filament\Admin\Resources\SchoolGrades\Pages\CreateSchoolGrade;
use App\Filament\Admin\Resources\SchoolGrades\Pages\EditSchoolGrade;
use App\Filament\Admin\Resources\SchoolGrades\Pages\ListSchoolGrades;
use App\Filament\Admin\Resources\SchoolGrades\Schemas\SchoolGradeForm;
use App\Filament\Admin\Resources\SchoolGrades\Tables\SchoolGradesTable;
use App\Modules\Institution\Models\SchoolGrade;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class SchoolGradeResource extends Resource
{
    protected static ?string $model = SchoolGrade::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string | UnitEnum | null $navigationGroup = 'Institución';

    protected static ?string $navigationLabel = 'Grados escolares';

    protected static ?string $modelLabel = 'Grado escolar';

    protected static ?string $pluralModelLabel = 'Grados escolares';

    public static function form(Schema $schema): Schema
    {
        return SchoolGradeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SchoolGradesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSchoolGrades::route('/'),
            'create' => CreateSchoolGrade::route('/create'),
            'edit' => EditSchoolGrade::route('/{record}/edit'),
        ];
    }
}
