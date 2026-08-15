<?php

namespace App\Filament\Academic\Resources\Projects\RelationManagers;

use App\Livewire\Shared\PrivateChatPanel;
use App\Models\User;
use App\Modules\Community\Actions\SendPrivateChatMessageAction;
use App\Modules\Community\Models\PrivateChatThread;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTeam;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Livewire;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

/**
 * shouldSkipAuthorization(): mismo motivo que ForumThreadsRelationManager
 * -- la autorización real (PrivateChatThreadPolicy) exige contexto
 * (proyecto+tipo+estudiante o equipo) que la autorización automática de
 * Filament no puede construir; cada acción declara su propio
 * Gate::allows()/Gate::authorize() explícito.
 *
 * "Ver conversación" embebe App\Livewire\Shared\PrivateChatPanel dentro
 * del modal (Schemas\Components\Livewire) -- el MISMO componente que usa
 * el estudiante y la página institucional, en vez de reconstruir el envío/
 * la lista de mensajes con acciones de Filament: un solo punto de verdad
 * para "quién puede enviar, quién puede ocultar", nunca duplicado acá.
 * modalSubmitAction(false): el componente embebido maneja su propio envío
 * (wire:submit interno), el modal de Filament no necesita su propio botón
 * de guardar.
 *
 * Los dos header actions ("Nuevo chat individual"/"Nuevo chat de equipo")
 * son la única vía para INICIAR un hilo desde este panel -- responder a uno
 * ya existente se hace desde "Ver conversación" con el hilo ya listado en
 * la tabla. Ambos reutilizan SendPrivateChatMessageAction (firstOrCreate),
 * así que elegir un estudiante/equipo que ya tiene hilo simplemente agrega
 * el mensaje al hilo existente, sin duplicarlo.
 */
class PrivateChatThreadsRelationManager extends RelationManager
{
    protected static string $relationship = 'privateChatThreads';

    protected static ?string $title = 'Chat privado';

    public static function shouldSkipAuthorization(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (string $state): string => $state === 'individual' ? 'Individual' : 'Equipo')
                    ->badge(),

                TextColumn::make('participant')
                    ->label('Con')
                    ->getStateUsing(fn (PrivateChatThread $record): string => $record->type === 'individual'
                        ? ($record->student?->name ?? '—')
                        : ($record->team?->name ?? '—')),

                TextColumn::make('messages_count')
                    ->label('Mensajes')
                    ->counts('messages'),

                TextColumn::make('created_at')
                    ->label('Iniciado')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Sin conversaciones todavía')
            ->headerActions([
                self::newIndividualAction(),
                self::newTeamAction(),
            ])
            ->recordActions([
                self::viewThreadAction(),
            ]);
    }

    protected static function newIndividualAction(): Action
    {
        return Action::make('newIndividual')
            ->label('Nuevo chat individual')
            ->modalHeading('Nuevo chat individual')
            ->modalSubmitActionLabel('Enviar')
            ->schema(fn (RelationManager $livewire): array => [
                Select::make('student_id')
                    ->label('Estudiante')
                    ->options(fn (): array => User::query()
                        ->role('student')
                        ->whereHas('schoolGrade', fn ($query) => $query->where('cycle_id', $livewire->getOwnerRecord()->cycle_id))
                        ->pluck('name', 'id')
                        ->all())
                    ->required()
                    ->searchable(),
                Textarea::make('content')
                    ->label('Mensaje')
                    ->required()
                    ->maxLength(2000),
            ])
            ->visible(fn (RelationManager $livewire): bool => Gate::allows('create', [PrivateChatThread::class, $livewire->getOwnerRecord(), 'individual', null, null]))
            ->action(function (array $data, RelationManager $livewire): void {
                /** @var Project $project */
                $project = $livewire->getOwnerRecord();
                $student = User::findOrFail($data['student_id']);

                Gate::authorize('create', [PrivateChatThread::class, $project, 'individual', $student, null]);

                app(SendPrivateChatMessageAction::class)->execute($project, 'individual', $student, null, auth()->user(), $data['content']);
            });
    }

    protected static function newTeamAction(): Action
    {
        return Action::make('newTeam')
            ->label('Nuevo chat de equipo')
            ->modalHeading('Nuevo chat de equipo')
            ->modalSubmitActionLabel('Enviar')
            ->schema(fn (RelationManager $livewire): array => [
                Select::make('team_id')
                    ->label('Equipo')
                    ->options(fn (): array => $livewire->getOwnerRecord()->teams()->pluck('name', 'id')->all())
                    ->required()
                    ->searchable(),
                Textarea::make('content')
                    ->label('Mensaje')
                    ->required()
                    ->maxLength(2000),
            ])
            ->visible(fn (RelationManager $livewire): bool => Gate::allows('create', [PrivateChatThread::class, $livewire->getOwnerRecord(), 'team', null, null])
                && $livewire->getOwnerRecord()->teams()->exists())
            ->action(function (array $data, RelationManager $livewire): void {
                /** @var Project $project */
                $project = $livewire->getOwnerRecord();
                $team = ProjectTeam::findOrFail($data['team_id']);

                Gate::authorize('create', [PrivateChatThread::class, $project, 'team', null, $team]);

                app(SendPrivateChatMessageAction::class)->execute($project, 'team', null, $team, auth()->user(), $data['content']);
            });
    }

    protected static function viewThreadAction(): Action
    {
        return Action::make('view')
            ->label('Ver conversación')
            ->modalHeading(fn (PrivateChatThread $record): string => $record->type === 'individual'
                ? "Chat individual — {$record->student?->name}"
                : "Chat de equipo — {$record->team?->name}")
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->schema(fn (PrivateChatThread $record): array => [
                Livewire::make(PrivateChatPanel::class, [
                    'project' => $record->project,
                    'type' => $record->type,
                    'student' => $record->student,
                    'team' => $record->team,
                ]),
            ]);
    }
}
