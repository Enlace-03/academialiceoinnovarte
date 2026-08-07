<?php

namespace App\Filament\Academic\Resources\Projects\RelationManagers;

use App\Models\User;
use App\Modules\Institution\Models\Group;
use App\Modules\Project\Models\ProjectTeam;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class ProjectTeamsRelationManager extends RelationManager
{
    protected static string $relationship = 'teams';

    protected static ?string $title = 'Equipos';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre'),

                TextColumn::make('group.name')
                    ->label('Grupo'),

                TextColumn::make('users_count')
                    ->label('Integrantes')
                    ->counts('users'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Crear equipo')
                    ->modalHeading('Crear equipo')
                    ->visible(fn (): bool => Gate::allows('update', $this->getOwnerRecord())),
            ])
            ->recordActions([
                self::manageMembersAction()
                    ->visible(fn (): bool => Gate::allows('update', $this->getOwnerRecord())),
                EditAction::make()
                    ->visible(fn (): bool => Gate::allows('update', $this->getOwnerRecord())),
                DeleteAction::make()
                    ->visible(fn (): bool => Gate::allows('update', $this->getOwnerRecord())),
            ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nombre')
                ->required(),

            Select::make('group_id')
                ->label('Grupo')
                ->options(fn () => Group::query()->pluck('name', 'id'))
                ->required()
                ->searchable(),
        ]);
    }

    protected static function manageMembersAction(): Action
    {
        return Action::make('manageMembers')
            ->label('Integrantes')
            ->icon('heroicon-o-user-group')
            ->schema(fn (ProjectTeam $record): array => [
                Repeater::make('members')
                    ->label('Integrantes')
                    ->schema([
                        Select::make('user_id')
                            ->label('Estudiante')
                            ->options(fn () => User::query()->role('student')->pluck('name', 'id'))
                            ->required()
                            ->searchable(),

                        Select::make('role_in_team')
                            ->label('Rol en el equipo')
                            ->options(config('project.team_roles')),
                    ])
                    ->default(fn (ProjectTeam $record): array => $record->users
                        ->map(fn (User $user): array => [
                            'user_id' => $user->id,
                            'role_in_team' => $user->pivot->role_in_team,
                        ])
                        ->toArray())
                    ->addActionLabel('Agregar integrante'),
            ])
            ->action(function (ProjectTeam $record, array $data): void {
                $sync = collect($data['members'] ?? [])
                    ->mapWithKeys(fn (array $member): array => [
                        $member['user_id'] => ['role_in_team' => $member['role_in_team'] ?? null],
                    ]);

                $record->users()->sync($sync);
            });
    }
}
