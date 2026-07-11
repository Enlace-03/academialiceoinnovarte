---
name: rubric-evaluation
description: Cargar esta skill cuando se trabaje con evaluaciones, rúbricas, niveles de desempeño, o cualquier pantalla que muestre resultados de evaluación. Cubre el enum, el flujo de evaluación y la presentación visual.
---

# Evaluación cualitativa — Liceo Innovarte

## El enum RubricLevel

```php
// app/Modules/Shared/Enums/RubricLevel.php
namespace App\Modules\Shared\Enums;

enum RubricLevel: string
{
    case NotAchieved       = 'not_achieved';
    case PartiallyAchieved = 'partially_achieved';
    case Achieved          = 'achieved';
    case Exceeded          = 'exceeded';

    public function label(): string
    {
        return match($this) {
            self::NotAchieved       => 'No alcanzó',
            self::PartiallyAchieved => 'Alcanzó medianamente',
            self::Achieved          => 'Alcanzó',
            self::Exceeded          => 'Superó',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::NotAchieved       => 'danger',   // Filament
            self::PartiallyAchieved => 'warning',
            self::Achieved          => 'success',
            self::Exceeded          => 'info',
        };
    }

    public function numericValue(): int
    {
        return match($this) {
            self::NotAchieved       => 1,
            self::PartiallyAchieved => 2,
            self::Achieved          => 3,
            self::Exceeded          => 4,
        };
    }

    // Para cálculos internos — NUNCA mostrar este valor al usuario
    public static function fromNumeric(int $value): self
    {
        return match($value) {
            1 => self::NotAchieved,
            2 => self::PartiallyAchieved,
            3 => self::Achieved,
            4 => self::Exceeded,
        };
    }
}
```

## Regla de oro: nunca mostrar el número

```php
// ✅ CORRECTO en Filament
TextColumn::make('level')
    ->badge()
    ->formatStateUsing(fn (string $state) => RubricLevel::from($state)->label())
    ->color(fn (string $state) => RubricLevel::from($state)->color()),

// ✅ CORRECTO en Blade
<x-rubric-badge :level="$result->level" />

// ❌ INCORRECTO — nunca mostrar el valor numérico
{{ $result->numericValue() }}  // NO
TextColumn::make('numeric_value'),  // NO
```

## Flujo de evaluación (EvaluateSubmissionAction)

```php
// app/Modules/Assessment/Actions/EvaluateSubmissionAction.php
final class EvaluateSubmissionAction
{
    public function execute(
        User $teacher,
        Submission $submission,
        array $criteriaResults, // ['criterion_id' => 'achieved', ...]
        ?string $feedback = null,
    ): Evaluation {
        abort_unless($teacher->can('evaluate', $submission), 403);
        abort_if($submission->evaluation()->exists(), 422, 'Esta entrega ya fue evaluada.');

        return DB::transaction(function () use ($teacher, $submission, $criteriaResults, $feedback) {
            $evaluation = Evaluation::create([
                'submission_id' => $submission->id,
                'evaluated_by'  => $teacher->id,
                'feedback'      => $feedback,
                'evaluated_at'  => now(),
            ]);

            foreach ($criteriaResults as $criterionId => $level) {
                EvaluationResult::create([
                    'evaluation_id'      => $evaluation->id,
                    'rubric_criterion_id'=> $criterionId,
                    'level'              => $level, // string del enum
                ]);
            }

            $submission->update(['status' => 'evaluated']);

            // Evento → listeners recalculan progreso, notifican padre
            event(new SubmissionEvaluated($evaluation));

            return $evaluation;
        });
    }
}
```

## Formulario de evaluación en Filament (modal)

```php
Tables\Actions\Action::make('evaluate')
    ->label('Evaluar')
    ->icon('heroicon-o-check-badge')
    ->visible(fn (Submission $record) => ! $record->evaluation()->exists())
    ->form(function (Submission $record) {
        $criteria = $record->expectedEvidence->rubric->criteria;
        return $criteria->map(fn ($criterion) =>
            Select::make("criteria.{$criterion->id}")
                ->label($criterion->name)
                ->options(collect(RubricLevel::cases())->mapWithKeys(
                    fn ($level) => [$level->value => $level->label()]
                ))
                ->required()
        )->concat([
            Textarea::make('feedback')->label('Comentario general')->nullable(),
        ])->toArray();
    })
    ->action(function (Submission $record, array $data) {
        app(EvaluateSubmissionAction::class)->execute(
            auth()->user(),
            $record,
            $data['criteria'],
            $data['feedback'] ?? null,
        );
    });
```

## Cálculo del nivel promedio (para student_metrics)

```php
// Promedio ponderado como valor numérico interno — solo para cálculos
$avgNumeric = EvaluationResult::whereIn('evaluation_id', $evaluationIds)
    ->get()
    ->avg(fn ($r) => RubricLevel::from($r->level)->numericValue());

// Guardar en student_metrics como decimal (1.00 - 4.00)
// NUNCA mostrar este decimal al usuario — convertir a texto/color siempre
```

## Presentación para padres (lenguaje humano)

```php
// En el dashboard del padre, traducir a frases legibles
$levelText = match(RubricLevel::from($level)) {
    RubricLevel::NotAchieved       => 'Necesita refuerzo en este tema',
    RubricLevel::PartiallyAchieved => 'Está en camino, puede mejorar',
    RubricLevel::Achieved          => 'Cumplió con lo esperado',
    RubricLevel::Exceeded          => 'Fue más allá de lo esperado',
};
```
