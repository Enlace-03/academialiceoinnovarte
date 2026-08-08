<?php

namespace App\Filament\Academic\Resources\Groups;

use App\Filament\Academic\Resources\Groups\Pages\ListGroups;
use App\Filament\Academic\Resources\Groups\Tables\GroupsTable;
use App\Modules\Institution\Models\Group;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Solo lectura + la acción "Entregar sesión" (Hito 3b-2) -- crear/editar/
 * borrar grupos sigue siendo exclusivo de /admin (GroupResource ahí, atado
 * a GroupPolicy con institution.groups.manage). Este Resource pisa
 * deliberadamente esa Policy con canViewAny()/canView() propios: si
 * heredara el comportamiento por defecto de Filament (auto-resolver
 * GroupPolicy para Group::class en cualquier panel), un teacher sin
 * institution.groups.manage jamás vería esta pantalla ni el botón que la
 * motiva. El criterio real de acceso aquí es "es personal" (isStaff()),
 * igual de amplio que ChatMessagePolicy — ver TODO.md #15.
 */
class GroupResource extends Resource
{
    protected static ?string $model = Group::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string | UnitEnum | null $navigationGroup = 'Institución';

    protected static ?string $navigationLabel = 'Grupos';

    protected static ?string $modelLabel = 'Grupo';

    protected static ?string $pluralModelLabel = 'Grupos';

    public static function canViewAny(): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    public static function table(Table $table): Table
    {
        return GroupsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGroups::route('/'),
        ];
    }
}
