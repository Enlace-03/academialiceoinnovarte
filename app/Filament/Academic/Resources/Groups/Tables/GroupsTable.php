<?php

namespace App\Filament\Academic\Resources\Groups\Tables;

use App\Modules\Community\Models\ChatMessage;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\Group;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class GroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('cycle.name')
                    ->label('Ciclo')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),

                TextColumn::make('year')
                    ->label('Año lectivo')
                    ->sortable(),

                TextColumn::make('users_count')
                    ->label('Estudiantes')
                    ->counts('users'),
            ])
            ->filters([
                SelectFilter::make('cycle_id')
                    ->label('Ciclo')
                    ->options(fn () => Cycle::query()->orderBy('order')->pluck('name', 'id')),
            ])
            ->defaultSort('year', 'desc')
            ->recordActions([
                self::grantSessionAction(),
            ]);
    }

    /**
     * Sin permiso nuevo (Hito 3b-2, decisión confirmada): reutiliza el
     * mismo criterio que ChatMessagePolicy::create() ya usa para "¿tiene
     * este staff autoridad operativa sobre este grupo?" -- hoy, cualquier
     * staff, cualquier grupo (TODO.md #15).
     *
     * ->url(), NO ->action() con schema/modal: es un enlace real a una
     * página plana fuera de Filament/Livewire (routes/web.php,
     * academic.group-sessions.create) -- ver el docblock de esa ruta para
     * el porqué. El auth-switch nunca ocurre dentro de un ciclo de vida de
     * Livewire.
     */
    protected static function grantSessionAction(): Action
    {
        return Action::make('grantSession')
            ->label('Entregar sesión')
            ->icon('heroicon-o-device-tablet')
            ->visible(fn (Group $record): bool => Gate::allows('create', [ChatMessage::class, $record]))
            ->url(fn (Group $record): string => route('academic.group-sessions.create', $record));
    }
}
