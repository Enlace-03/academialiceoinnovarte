<?php

declare(strict_types=1);

namespace App\Filament\Academic\Resources\GalleryPosts\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GalleryPostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Título')
                    ->searchable(),

                TextColumn::make('project.title')
                    ->label('Proyecto')
                    ->placeholder('General (institucional)'),

                TextColumn::make('createdBy.name')
                    ->label('Publicado por'),

                TextColumn::make('photos_count')
                    ->label('Fotos')
                    ->counts('photos'),

                TextColumn::make('published_at')
                    ->label('Publicado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('published_at', 'desc');
    }
}
