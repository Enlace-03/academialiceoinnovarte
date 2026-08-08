<?php

namespace App\Filament\Academic\Resources\Projects\RelationManagers;

use App\Modules\Community\Actions\HideCommunityContentAction;
use App\Modules\Community\Models\ForumPost;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

/**
 * Vista plana de participación entre todos los hilos del proyecto (insumo
 * para el futuro Tracking): quién publicó, cuántos likes tiene cada
 * publicación. Solo lectura + moderar — crear/responder posts es del
 * portal de estudiante (Hito 3b), no de este panel.
 */
class ForumPostsRelationManager extends RelationManager
{
    protected static string $relationship = 'forumPosts';

    protected static ?string $title = 'Foro — participación';

    public static function shouldSkipAuthorization(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('content')
            ->columns([
                TextColumn::make('thread.title')
                    ->label('Hilo')
                    ->limit(30),

                TextColumn::make('user.name')
                    ->label('Autor'),

                TextColumn::make('content')
                    ->label('Contenido')
                    ->limit(60),

                IconColumn::make('parent_post_id')
                    ->label('Respuesta')
                    ->boolean()
                    ->getStateUsing(fn (ForumPost $record): bool => $record->parent_post_id !== null),

                TextColumn::make('likes_count')
                    ->label('Likes')
                    ->counts('likes'),

                IconColumn::make('is_hidden')
                    ->label('Oculto')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                self::hidePostAction(),
            ]);
    }

    protected static function hidePostAction(): Action
    {
        return Action::make('hide')
            ->label('Ocultar')
            ->icon('heroicon-o-eye-slash')
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (ForumPost $record): bool => ! $record->is_hidden && Gate::allows('hide', $record))
            ->action(function (ForumPost $record): void {
                app(HideCommunityContentAction::class)->execute($record, auth()->user());
            });
    }
}
