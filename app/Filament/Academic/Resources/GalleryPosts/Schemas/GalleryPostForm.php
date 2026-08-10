<?php

declare(strict_types=1);

namespace App\Filament\Academic\Resources\GalleryPosts\Schemas;

use App\Modules\Project\Models\Project;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class GalleryPostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('project_id')
                ->label('Proyecto (vacío = general/institucional)')
                ->options(fn () => Project::query()->pluck('title', 'id'))
                ->searchable()
                ->helperText('Si se deja vacío, la publicación es visible para todo el colegio. Si se elige un proyecto, hereda su audiencia (mismo ciclo).'),

            TextInput::make('title')
                ->label('Título')
                ->required()
                ->columnSpanFull(),

            Textarea::make('caption')
                ->label('Texto (opcional)')
                ->rows(3)
                ->columnSpanFull(),

            DateTimePicker::make('published_at')
                ->label('Fecha de publicación')
                ->default(now())
                ->required()
                ->helperText('Una fecha futura mantiene la publicación oculta en el portal hasta ese momento.'),

            Repeater::make('photos')
                ->relationship()
                ->label('Fotos')
                ->schema([
                    Hidden::make('file_disk')->default('local'),

                    FileUpload::make('file_path')
                        ->label('Foto')
                        ->image()
                        ->disk('local')
                        ->directory('gallery-photos')
                        ->storeFileNamesIn('original_filename')
                        ->maxSize(10240)
                        ->required(),
                ])
                ->orderColumn('order')
                ->reorderable()
                ->collapsible()
                ->addActionLabel('Agregar foto')
                ->columnSpanFull(),
        ]);
    }
}
