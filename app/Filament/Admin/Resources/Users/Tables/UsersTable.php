<?php

namespace App\Filament\Admin\Resources\Users\Tables;

use App\Models\User;
use App\Modules\Institution\Models\Group;
use App\Support\PermissionLabels;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('group.schoolGrade'))
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => PermissionLabels::role($state))
                    ->separator(','),

                TextColumn::make('group.name')
                    ->label('Grado / Grupo')
                    ->getStateUsing(fn (User $record): string => $record->group
                        ? "{$record->group->schoolGrade->name} - {$record->group->name}"
                        : '—'),

                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label('Rol')
                    ->options(fn () => Role::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->map(fn (string $name): string => PermissionLabels::role($name))
                        ->all())
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $q, $roleId) => $q->whereHas(
                                'roles',
                                fn (Builder $q2) => $q2->where('roles.id', $roleId)
                            ),
                        );
                    }),

                TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->placeholder('Todos')
                    ->trueLabel('Activos')
                    ->falseLabel('Inactivos'),

                SelectFilter::make('group_id')
                    ->label('Grado / Grupo')
                    ->options(fn () => Group::query()
                        ->with('schoolGrade')
                        ->get()
                        ->sortBy(fn (Group $group) => [$group->schoolGrade->level, $group->name])
                        ->mapWithKeys(fn (Group $group) => [
                            $group->id => "{$group->schoolGrade->name} - {$group->name} ({$group->year})",
                        ])
                        ->all()),
            ])
            ->defaultSort('name');
    }
}
