<?php

namespace App\Filament\Academic\Resources\Students;

use App\Filament\Academic\Resources\Students\Pages\ListStudents;
use App\Filament\Academic\Resources\Students\Tables\StudentsTable;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Existe hoy únicamente para alojar la moderación de foto de perfil
 * (students.photo.moderate) -- ver StudentsTable. El resto de la gestión de
 * estudiantes (crear/editar/vincular acudientes) sigue siendo exclusiva de
 * /admin (UserResource ahí, gobernada por users.*).
 *
 * canViewAny()/canView() pisan a propósito la resolución automática de
 * Filament hacia UserPolicy (ya registrada para User::class): esa Policy
 * gobierna users.view/users.update, NO el permiso atómico
 * students.photo.moderate que gobierna esta pantalla -- mismo criterio que
 * GroupResource ya usa en este mismo panel para no heredar GroupPolicy.
 *
 * /admin está reservado a permisos users.* / institution.* (decisión de
 * arquitectura ya fijada, ver el hito que movió ProjectResource completo a
 * /academia) -- students.photo.moderate es deliberadamente un permiso
 * atómico aparte, así que esta pantalla vive en /academia: el acceso al
 * panel solo exige ser personal (isStaff(), vía canAccessPanel('academic')),
 * y es esta Resource la que exige el permiso puntual para lo que realmente
 * importa (ver/usar las acciones). Confirmado en vivo: un usuario con
 * students.photo.moderate y CERO permisos users.* / institution.* no puede
 * entrar a /admin en absoluto, pero sí a /academia por ser personal.
 */
class StudentResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string | UnitEnum | null $navigationGroup = 'Institución';

    protected static ?string $navigationLabel = 'Estudiantes';

    protected static ?string $modelLabel = 'Estudiante';

    protected static ?string $pluralModelLabel = 'Estudiantes';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermissionTo('students.photo.moderate') ?? false;
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->hasPermissionTo('students.photo.moderate') ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->role('student');
    }

    public static function table(Table $table): Table
    {
        return StudentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStudents::route('/'),
        ];
    }
}
