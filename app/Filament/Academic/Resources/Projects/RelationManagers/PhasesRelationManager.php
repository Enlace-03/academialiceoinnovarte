<?php

namespace App\Filament\Academic\Resources\Projects\RelationManagers;

use App\Modules\Assessment\Models\Rubric;
use App\Modules\Project\Models\ExpectedEvidence;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\Resource as ProjectResourceModel;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

/**
 * Las 4 fases institucionales ya nacen creadas (ver ProjectObserver) y no se
 * crean ni eliminan a mano: solo se edita su nombre/descripción y se
 * gestionan sus guías, recursos y evidencias esperadas anidadas.
 */
class PhasesRelationManager extends RelationManager
{
    protected static string $relationship = 'phases';

    protected static ?string $title = 'Fases';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('order')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Fase'),

                TextColumn::make('guides_count')
                    ->label('Guías')
                    ->counts('guides'),

                TextColumn::make('resources_count')
                    ->label('Recursos')
                    ->counts('resources'),

                TextColumn::make('expected_evidences_count')
                    ->label('Evidencias')
                    ->counts('expectedEvidences'),
            ])
            ->defaultSort('order')
            ->recordActions([
                EditAction::make()
                    ->modalWidth('4xl')
                    ->visible(fn (): bool => Gate::allows('managePhases', $this->getOwnerRecord())),
            ]);
    }

    public function form(Schema $schema): Schema
    {
        /** @var Project $project */
        $project = $this->getOwnerRecord();

        return $schema->components([
            TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->columnSpanFull(),

            Textarea::make('description')
                ->label('Descripción')
                ->rows(2)
                ->columnSpanFull(),

            Repeater::make('guides')
                ->relationship()
                ->label('Guías')
                ->schema([
                    TextInput::make('title')
                        ->label('Título')
                        ->required(),

                    Textarea::make('content')
                        ->label('Contenido (HTML/Markdown)')
                        ->rows(3),
                ])
                ->collapsible()
                ->columnSpanFull(),

            Repeater::make('resources')
                ->relationship()
                ->label('Recursos')
                ->visible(fn (): bool => Gate::allows('manageResources', $project))
                ->schema([
                    TextInput::make('title')
                        ->label('Título')
                        ->required(),

                    Select::make('type')
                        ->label('Tipo')
                        ->options(ProjectResourceModel::TYPES)
                        ->required(),

                    TextInput::make('url_or_path')
                        ->label('URL o ruta')
                        ->required(),
                ])
                ->collapsible()
                ->columnSpanFull(),

            Repeater::make('expectedEvidences')
                ->relationship()
                ->label('Evidencias esperadas')
                ->schema([
                    Select::make('type')
                        ->label('Tipo')
                        ->options(ExpectedEvidence::TYPES)
                        ->required(),

                    Textarea::make('description')
                        ->label('Descripción')
                        ->rows(2),

                    Toggle::make('is_required')
                        ->label('Obligatoria')
                        ->default(true),

                    TextInput::make('alternative_group')
                        ->label('Grupo alternativo')
                        ->helperText('Evidencias con el mismo valor son mutuamente excluyentes (el equipo entrega una, no todas).'),

                    Select::make('rubric_id')
                        ->label('Rúbrica')
                        ->options(fn () => Rubric::query()->pluck('name', 'id'))
                        ->searchable()
                        ->helperText('Banco de rúbricas reutilizable — la misma rúbrica puede usarse en varios proyectos.'),
                ])
                ->collapsible()
                ->columnSpanFull(),
        ]);
    }
}
