<?php

namespace App\Filament\Academic\Resources\Projects\RelationManagers;

use App\Modules\Community\Actions\CreateForumThreadAction;
use App\Modules\Community\Actions\HideCommunityContentAction;
use App\Modules\Community\Models\ForumThread;
use App\Modules\Project\Models\Project;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

/**
 * Crear un hilo pasa por CreateForumThreadAction (no ->relationship() de
 * Filament) porque ese es el punto único que decide project_id/created_by —
 * igual criterio que ExpectedEvidencesRelationManager con RegisterSubmissionAction.
 * Ocultar es de un solo sentido en este hito (sin "mostrar de nuevo" desde
 * la UI todavía) — HideCommunityContentAction se comparte con posts y chat.
 *
 * shouldSkipAuthorization(): la autorización automática de Filament para
 * CreateAction llama a Gate::authorize('create', ForumThread::class) sin
 * contexto del Project dueño — pero ForumThreadPolicy::create() exige ese
 * Project para decidir own/all. Se desactiva la autorización automática y se
 * declara explícitamente con Gate::allows('create', [ForumThread::class, $project])
 * en el propio ->visible() de la acción.
 */
class ForumThreadsRelationManager extends RelationManager
{
    protected static string $relationship = 'forumThreads';

    protected static ?string $title = 'Foro — hilos';

    public static function shouldSkipAuthorization(): bool
    {
        return true;
    }

    public function form(Schema $schema): Schema
    {
        /** @var Project $project */
        $project = $this->getOwnerRecord();

        return $schema->components([
            TextInput::make('title')
                ->label('Título')
                ->required()
                ->columnSpanFull(),

            Select::make('phase_id')
                ->label('Fase (opcional)')
                ->options(fn () => $project->phases()->pluck('name', 'id'))
                ->helperText('Vacío: hilo general del proyecto, no específico de una fase.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')
                    ->label('Título')
                    ->limit(50),

                TextColumn::make('phase.name')
                    ->label('Fase')
                    ->placeholder('General'),

                TextColumn::make('creator.name')
                    ->label('Creado por'),

                TextColumn::make('posts_count')
                    ->label('Publicaciones')
                    ->counts('posts'),

                IconColumn::make('is_hidden')
                    ->label('Oculto')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Sin hilos todavía')
            ->emptyStateDescription('Cree un hilo para empezar.')
            ->headerActions([
                CreateAction::make()
                    ->label('Nuevo hilo')
                    ->modalHeading('Nuevo hilo')
                    ->modalSubmitActionLabel('Crear')
                    ->schema(fn (Schema $schema): Schema => $this->form($schema))
                    ->visible(fn (): bool => Gate::allows('create', [ForumThread::class, $this->getOwnerRecord()]))
                    ->using(function (array $data): ForumThread {
                        /** @var Project $project */
                        $project = $this->getOwnerRecord();

                        return app(CreateForumThreadAction::class)->execute($project, auth()->user(), $data);
                    }),
            ])
            ->recordActions([
                self::hideThreadAction(),
            ]);
    }

    protected static function hideThreadAction(): Action
    {
        return Action::make('hide')
            ->label('Ocultar')
            ->icon('heroicon-o-eye-slash')
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (ForumThread $record): bool => ! $record->is_hidden && Gate::allows('hide', $record))
            ->action(function (ForumThread $record): void {
                app(HideCommunityContentAction::class)->execute($record, auth()->user());
            });
    }
}
