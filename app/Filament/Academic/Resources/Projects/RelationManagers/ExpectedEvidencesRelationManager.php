<?php

namespace App\Filament\Academic\Resources\Projects\RelationManagers;

use App\Models\User;
use App\Modules\Assessment\Actions\EvaluateSubmissionAction;
use App\Modules\Assessment\Actions\RegisterSubmissionAction;
use App\Modules\Assessment\Models\Evaluation;
use App\Modules\Assessment\Models\RubricLevel;
use App\Modules\Assessment\Models\Submission;
use App\Modules\Project\Models\ExpectedEvidence;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

/**
 * Sin portal de estudiante todavía: el personal registra manualmente que un
 * estudiante entregó, y evalúa con la rúbrica asociada en el mismo lugar.
 * Las evidencias mismas se crean/editan desde PhasesRelationManager — aquí
 * solo se gestionan entregas y evaluación, por eso no hay CreateAction.
 */
class ExpectedEvidencesRelationManager extends RelationManager
{
    protected static string $relationship = 'expectedEvidences';

    protected static ?string $title = 'Evidencias — entregas y evaluación';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('phase.name')
                    ->label('Fase'),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (string $state): string => \App\Modules\Project\Models\ExpectedEvidence::TYPES[$state] ?? $state),

                TextColumn::make('description')
                    ->label('Descripción')
                    ->limit(40),

                TextColumn::make('rubric.name')
                    ->label('Rúbrica')
                    ->placeholder('Sin rúbrica'),

                TextColumn::make('submissions_count')
                    ->label('Entregas')
                    ->counts('submissions'),
            ])
            ->recordActions([
                self::registerSubmissionAction()
                    ->visible(fn (ExpectedEvidence $record): bool => Gate::allows('update', $record->phase->project)),

                self::evaluateSubmissionsAction()
                    ->visible(fn (ExpectedEvidence $record): bool => $record->rubric_id !== null
                        && $record->submissions()->exists()
                        && auth()->user()->hasPermissionTo('submissions.evaluate')
                        && Gate::allows('update', $record->phase->project)),
            ]);
    }

    protected static function registerSubmissionAction(): Action
    {
        return Action::make('registerSubmission')
            ->label('Registrar entrega')
            ->icon('heroicon-o-inbox-arrow-down')
            ->schema([
                Select::make('student_id')
                    ->label('Estudiante')
                    ->options(fn () => User::query()->role('student')->pluck('name', 'id'))
                    ->required()
                    ->searchable(),

                Textarea::make('text_content')
                    ->label('Contenido (si la evidencia es de texto)')
                    ->rows(3),

                Hidden::make('file_disk')->default('local'),

                FileUpload::make('file_path')
                    ->label('Archivo (si la evidencia es de archivo)')
                    ->disk('local')
                    ->directory('submissions')
                    ->storeFileNamesIn('original_filename'),
            ])
            ->action(function (ExpectedEvidence $record, array $data): void {
                $student = User::findOrFail($data['student_id']);

                app(RegisterSubmissionAction::class)->execute($record, $student, $data);
            });
    }

    protected static function evaluateSubmissionsAction(): Action
    {
        return Action::make('evaluateSubmissions')
            ->label('Evaluar entregas')
            ->icon('heroicon-o-check-badge')
            ->modalWidth('4xl')
            ->schema(function (ExpectedEvidence $record): array {
                $criteria = $record->rubric->criteria;
                $levelOptions = RubricLevel::query()->orderBy('order')->pluck('label', 'id');

                $criteriaFields = $criteria->map(fn ($criterion) => Select::make("results.{$criterion->id}")
                    ->label($criterion->name)
                    ->options($levelOptions)
                    ->required())->all();

                return [
                    Repeater::make('submissions')
                        ->label('Entregas')
                        ->schema([
                            Hidden::make('submission_id'),

                            Placeholder::make('student')
                                ->label('Estudiante')
                                ->content(fn (Get $get): string => Submission::find($get('submission_id'))?->student?->name ?? '—'),

                            ...$criteriaFields,

                            Textarea::make('feedback')
                                ->label('Comentario general')
                                ->rows(2),
                        ])
                        ->columns(2)
                        ->addable(false)
                        ->deletable(false)
                        ->default(fn (): array => $record->submissions()
                            ->with(['evaluations' => fn ($query) => $query->where('evaluator_type', 'teacher')])
                            ->get()
                            ->map(function (Submission $submission): array {
                                $evaluation = $submission->evaluations->first();

                                return [
                                    'submission_id' => $submission->id,
                                    'results' => $evaluation
                                        ? $evaluation->results()->pluck('rubric_level_id', 'rubric_criterion_id')->toArray()
                                        : [],
                                    'feedback' => $evaluation?->feedback,
                                ];
                            })
                            ->all()),
                ];
            })
            ->action(function (array $data): void {
                foreach ($data['submissions'] as $row) {
                    $submission = Submission::findOrFail($row['submission_id']);

                    Gate::authorize('create', [Evaluation::class, $submission]);

                    app(EvaluateSubmissionAction::class)->execute(
                        auth()->user(),
                        $submission,
                        $row['results'] ?? [],
                        $row['feedback'] ?? null,
                    );
                }
            });
    }
}
