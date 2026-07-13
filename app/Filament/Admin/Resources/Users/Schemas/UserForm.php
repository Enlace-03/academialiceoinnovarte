<?php

namespace App\Filament\Admin\Resources\Users\Schemas;

use App\Models\User;
use App\Support\PermissionLabels;
use App\Rules\WithinDelegationCeiling;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        /** @var User $actingUser */
        $actingUser = Auth::user();

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
                        ->label('Roles')
                        ->rule(fn () => new WithinDelegationCeiling(
                            $actingUser->assignableRoles()->pluck('id')->all()
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
