<?php

namespace App\Filament\Academic\Resources\Projects\RelationManagers;

use App\Models\User;
use App\Modules\Assessment\Actions\EvaluateSubmissionAction;
use App\Modules\Assessment\Actions\RegisterSubmissionAction;
use App\Modules\Assessment\Models\Evaluation;
use App\Modules\Assessment\Models\RubricLevel;
use App\Modules\Assessment\Models\Submission;
use App\Modules\Assessment\Models\SubmissionAttachment;
use App\Modules\Project\Models\ExpectedEvidence;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

/**
 * Sin portal de estudiante todavía: el personal registra manualmente que un
 * estudiante entregó, y evalúa con la rúbrica asociada en el mismo lugar.
 * Las evidencias mismas se crean/editan desde PhasesRelationManager — aquí
 * solo se gestionan entregas y evaluación, por eso no hay CreateAction.
 *
 * registerSubmissionAction() (Hito 3b-3): el modal recarga los adjuntos ya
 * existentes al elegir estudiante (Select::make('student_id')->live()
 * ->afterStateUpdated(...)) en vez de asumir que siempre parte de una
 * entrega nueva -- reabrir el modal para un estudiante que ya tenía entrega
 * edita en su lugar (RegisterSubmissionAction reconcilia por existing_id),
 * no la reemplaza a ciegas. FileUpload::make('file_path') vive DENTRO del
 * Repeater (uno por adjunto), a diferencia del único FileUpload de antes de
 * este hito -- Filament ya guarda el archivo en disco antes de que corra
 * action(), por eso el closure arma 'stored_path' en vez de un objeto de
 * archivo (ver docblock de RegisterSubmissionAction y TODO.md #28).
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
            ->schema(fn (ExpectedEvidence $record): array => [
                Select::make('student_id')
                    ->label('Estudiante')
                    ->options(fn () => User::query()->role('student')->pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set) use ($record): void {
                        $existing = Submission::where('expected_evidence_id', $record->id)
                            ->where('student_id', $state)
                            ->with('attachments')
                            ->first();

                        $set('text_content', $existing?->text_content);
                        $set('attachments', $existing?->attachments->map(fn (SubmissionAttachment $attachment): array => [
                            'existing_id' => $attachment->id,
                            'type' => $attachment->type,
                            'url' => $attachment->url,
                        ])->all() ?? []);
                    }),

                Textarea::make('text_content')
                    ->label('Contenido (si la evidencia es de texto)')
                    ->rows(3),

                Repeater::make('attachments')
                    ->label('Adjuntos')
                    ->schema([
                        Hidden::make('existing_id'),

                        Select::make('type')
                            ->label('Tipo')
                            ->options(['photo' => 'Foto', 'link' => 'Enlace'])
                            ->default('photo')
                            ->required()
                            ->live(),

                        FileUpload::make('file_path')
                            ->label('Foto')
                            ->image()
                            ->disk('local')
                            ->directory('submissions')
                            ->storeFileNamesIn('original_filename')
                            ->visible(fn (Get $get): bool => $get('type') === 'photo' && ! $get('existing_id')),

                        TextInput::make('url')
                            ->label('URL')
                            ->url()
                            ->visible(fn (Get $get): bool => $get('type') === 'link'),
                    ])
                    ->addActionLabel('Agregar adjunto')
                    ->columnSpanFull(),
            ])
            ->action(function (ExpectedEvidence $record, array $data): void {
                $student = User::findOrFail($data['student_id']);

                $attachments = collect($data['attachments'] ?? [])->map(function (array $row): array {
                    if (! empty($row['existing_id'])) {
                        return ['type' => $row['type'], 'existing_id' => (int) $row['existing_id']];
                    }

                    if ($row['type'] === 'photo') {
                        return [
                            'type' => 'photo',
                            'stored_path' => $row['file_path'],
                            'original_filename' => $row['original_filename'] ?? null,
                        ];
                    }

                    return ['type' => 'link', 'url' => $row['url']];
                })->all();

                app(RegisterSubmissionAction::class)->execute($record, $student, [
                    'text_content' => $data['text_content'] ?? null,
                    'attachments' => $attachments,
                ]);
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

                            Placeholder::make('attachments')
                                ->label('Adjuntos')
                                ->content(fn (Get $get) => view('filament.academic.submission-attachments-list', [
                                    'attachments' => Submission::find($get('submission_id'))?->attachments ?? collect(),
                                ]))
                                ->columnSpanFull(),

                            Placeholder::make('rubricReference')
                                ->label('Rúbrica')
                                ->content(function (Get $get) use ($criteria): \Illuminate\Contracts\View\View {
                                    $submission = Submission::with([
                                        'evaluations' => fn ($query) => $query->where('evaluator_type', 'teacher')->with('results.rubricLevel'),
                                    ])->find($get('submission_id'));

                                    $evaluation = $submission?->evaluations->first();

                                    $resultsByCriterion = $evaluation
                                        ? $evaluation->results->mapWithKeys(fn ($result) => [$result->rubric_criterion_id => $result->rubricLevel])->all()
                                        : [];

                                    return view('components.rubric-criteria-table', [
                                        'criteria' => $criteria,
                                        'resultsByCriterion' => $resultsByCriterion,
                                    ]);
                                })
                                ->columnSpanFull(),

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
