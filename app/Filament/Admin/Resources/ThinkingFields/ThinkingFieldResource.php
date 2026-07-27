<?php

namespace App\Filament\Admin\Resources\ThinkingFields;

use App\Filament\Admin\Resources\ThinkingFields\Pages\CreateThinkingField;
use App\Filament\Admin\Resources\ThinkingFields\Pages\EditThinkingField;
use App\Filament\Admin\Resources\ThinkingFields\Pages\ListThinkingFields;
use App\Filament\Admin\Resources\ThinkingFields\Schemas\ThinkingFieldForm;
use App\Filament\Admin\Resources\ThinkingFields\Tables\ThinkingFieldsTable;
use App\Modules\Institution\Models\ThinkingField;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class ThinkingFieldResource extends Resource
{
    protected static ?string $model = ThinkingField::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string | UnitEnum | null $navigationGroup = 'Institución';

    protected static ?string $navigationLabel = 'Campos de pensamiento';

    protected static ?string $modelLabel = 'Campo de pensamiento';

    protected static ?string $pluralModelLabel = 'Campos de pensamiento';

    public static function form(Schema $schema): Schema
    {
        return ThinkingFieldForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ThinkingFieldsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListThinkingFields::route('/'),
            'create' => CreateThinkingField::route('/create'),
            'edit' => EditThinkingField::route('/{record}/edit'),
        ];
    }
}
