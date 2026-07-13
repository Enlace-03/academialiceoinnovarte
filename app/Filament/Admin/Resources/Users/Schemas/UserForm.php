<?php

namespace App\Filament\Admin\Resources\Users\Schemas;

use App\Models\User;
use App\Modules\Institution\Models\Group;
use App\Modules\Institution\Models\Institution;
use App\Modules\Institution\Models\SchoolGrade;
use App\Support\PermissionLabels;
use App\Rules\GroupRequiresStudentRole;
use App\Rules\WithinDelegationCeiling;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        /** @var User $actingUser */
        $actingUser = Auth::user();

        $studentRoleId = Role::where('name', 'student')->value('id');

        return $schema->components([
            Section::make('Datos básicos')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nombre completo')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('email')
                        ->label('Correo institucional')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),

                    TextInput::make('document_number')
                        ->label('Documento de identidad')
                        ->maxLength(50),

                    TextInput::make('password')
                        ->label('Contraseña')
                        ->password()
                        ->revealable()
                        ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->visibleOn(['create'])
                        ->maxLength(255),

                    Toggle::make('is_active')
                        ->label('Usuario activo')
                        ->default(true)
                        ->inline(false),
                ]),

            Section::make('Rol')
                ->description('Cada rol ya trae un conjunto de permisos predefinido. Solo ves los roles que están dentro de tu propio techo de delegación.')
                ->schema([
                    Select::make('roles')
                        ->relationship(
                            name: 'roles',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn ($query) => $query->when(
                                ! $actingUser->isSuperAdmin(),
                                fn ($q) => $q->whereIn('id', $actingUser->assignableRoles()->pluck('id'))
                            ),
                        )
                        ->getOptionLabelFromRecordUsing(fn ($record) => PermissionLabels::role($record->name))
                        ->multiple()
                        ->preload()
                        ->live()
                        ->label('Roles')
                        ->rule(fn () => new WithinDelegationCeiling(
                            $actingUser->assignableRoles()->pluck('id')->all()
                        )),
                ]),

            Section::make('Grado y grupo')
                ->description('Solo aplica a usuarios con rol Estudiante.')
                ->columns(2)
                ->schema([
                    Select::make('school_grade_filter')
                        ->label('Grado')
                        ->live()
                        ->dehydrated(false)
                        ->visible(fn (Get $get) => in_array($studentRoleId, $get('roles') ?? []))
                        ->options(fn () => SchoolGrade::query()
                            ->where('institution_id', Institution::query()->value('id'))
                            ->orderBy('level')
                            ->pluck('name', 'id'))
                        ->afterStateHydrated(function (Select $component, ?User $record) {
                            $component->state($record?->group?->school_grade_id);
                        }),

                    Select::make('group_id')
                        ->label('Grupo')
                        ->live()
                        ->dehydratedWhenHidden()
                        ->visible(fn (Get $get) => in_array($studentRoleId, $get('roles') ?? []))
                        ->options(function (Get $get, ?User $record) {
                            $schoolGradeId = $get('school_grade_filter');

                            if (! $schoolGradeId) {
                                return [];
                            }

                            return Group::query()
                                ->where('school_grade_id', $schoolGradeId)
                                ->where(fn ($query) => $query
                                    ->where('year', config('school.current_academic_year'))
                                    ->when(
                                        $record?->group_id,
                                        fn ($q, $groupId) => $q->orWhere('id', $groupId)
                                    ))
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (Group $group) => [$group->id => "{$group->name} ({$group->year})"])
                                ->all();
                        })
                        ->rule(fn (Get $get) => new GroupRequiresStudentRole(
                            in_array($studentRoleId, $get('roles') ?? [])
                        )),
                ]),

            Section::make('Permisos individuales adicionales')
                ->description('Encima del/los rol(es) elegido(s) arriba. Solo puedes otorgar permisos que tú mismo posees (techo de delegación).')
                ->visible(fn () => $actingUser->canGrantPermissions())
                ->schema([
                    CheckboxList::make('permissions')
                        ->relationship(
                            name: 'permissions',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn ($query) => $query
                                ->when(
                                    ! $actingUser->isSuperAdmin(),
                                    fn ($q) => $q->whereIn('name', $actingUser->assignablePermissionNames())
                                )
                                ->orderBy('name'),
                        )
                        ->getOptionLabelFromRecordUsing(fn ($record) => PermissionLabels::permission($record->name))
                        ->bulkToggleable()
                        ->searchable()
                        ->columns(2)
                        ->label('Permisos')
                        ->rule(fn () => new WithinDelegationCeiling(
                            Permission::whereIn('name', $actingUser->assignablePermissionNames())->pluck('id')->all()
                        )),
                ]),
        ]);
    }

}
