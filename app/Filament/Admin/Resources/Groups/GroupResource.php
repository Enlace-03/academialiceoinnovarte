<?php

namespace App\Filament\Admin\Resources\Groups;

use App\Filament\Admin\Resources\Groups\Pages\CreateGroup;
use App\Filament\Admin\Resources\Groups\Pages\EditGroup;
use App\Filament\Admin\Resources\Groups\Pages\ListGroups;
use App\Filament\Admin\Resources\Groups\Schemas\GroupForm;
use App\Filament\Admin\Resources\Groups\Tables\GroupsTable;
use App\Modules\Institution\Models\Group;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class GroupResource extends Resource
{
    protected static ?string $model = Group::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string | UnitEnum | null $navigationGroup = 'Institución';

    protected static ?string $navigationLabel = 'Grupos';

    protected static ?string $modelLabel = 'Grupo';

    protected static ?string $pluralModelLabel = 'Grupos';

    public static function form(Schema $schema): Schema
    {
        return GroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GroupsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGroups::route('/'),
            'create' => CreateGroup::route('/create'),
            'edit' => EditGroup::route('/{record}/edit'),
        ];
    }
}
