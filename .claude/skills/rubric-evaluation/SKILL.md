---
name: rubric-evaluation
description: Cargar esta skill cuando se trabaje con evaluaciones, rúbricas, niveles de desempeño, o cualquier pantalla que muestre resultados de evaluación. Cubre el catálogo de niveles, el flujo de evaluación y la presentación visual.
---

# Evaluación cualitativa — Liceo Innovarte

## Los niveles viven en una tabla catálogo, no en un enum

Decisión del Hito 2: `rubric_levels` es una tabla catálogo global (sin `institution_id`),
sembrada por `RubricLevelSeeder`, no un ENUM de base de datos ni un enum PHP — así se
puede agregar un quinto nivel editando datos, sin migración.

Escala vigente (única, las anteriores quedaron descartadas):

| key | label | color | order |
|---|---|---|---|
| `inicio` | Inicio | rojo (`danger`) | 1 |
| `en_proceso` | En proceso | naranja (`warning`) | 2 |
| `logro_esperado` | Logro esperado | amarillo (`amber`) | 3 |
| `logro_destacado` | Logro destacado | verde (`success`) | 4 |

`EvaluationResult.rubric_level_id` es una FK real a `rubric_levels.id` — nunca un
string ni un número crudo.

## Regla de oro: nunca mostrar el número

```php
// ✅ CORRECTO en Filament — el label y el color vienen del registro relacionado
TextColumn::make('rubricLevel.label')
    ->badge()
    ->color(fn (EvaluationResult $record) => $record->rubricLevel->color),

// ❌ INCORRECTO — nunca mostrar rubric_levels.order ni ningún valor numérico
TextColumn::make('rubricLevel.order'),  // NO
```

## Flujo de evaluación (EvaluateSubmissionAction)

Una entrega puede tener **más de una evaluación** (una por `evaluator_type`: hoy solo
`teacher`; `self`/`peer` quedan reservados para cuando se active auto/coevaluación).
`unique(submission_id, evaluator_type)` es lo que lo permite sin duplicar evaluaciones
del mismo tipo.

```php
// app/Modules/Assessment/Actions/EvaluateSubmissionAction.php
final class EvaluateSubmissionAction
{
    public function execute(
        User $evaluator,
        Submission $submission,
        array $criteriaResults, // ['criterion_id' => rubric_level_id, ...]
        ?string $feedback = null,
        string $evaluatorType = 'teacher',
    ): Evaluation {
        return DB::transaction(function () use ($evaluator, $submission, $criteriaResults, $feedback, $evaluatorType) {
            $evaluation = Evaluation::updateOrCreate(
                ['submission_id' => $submission->id, 'evaluator_type' => $evaluatorType],
                ['evaluated_by' => $evaluator->id, 'feedback' => $feedback, 'evaluated_at' => now()],
            );

            foreach ($criteriaResults as $criterionId => $rubricLevelId) {
                EvaluationResult::updateOrCreate(
                    ['evaluation_id' => $evaluation->id, 'rubric_criterion_id' => $criterionId],
                    ['rubric_level_id' => $rubricLevelId],
                );
            }

            return $evaluation;
        });
    }
}
```

## Formulario de evaluación en Filament (modal)

```php
Action::make('evaluate')
    ->label('Evaluar')
    ->schema(function (ExpectedEvidence $record) {
        $criteria = $record->rubric->criteria;
        return $criteria->map(fn ($criterion) =>
            Select::make("criteria.{$criterion->id}")
                ->label($criterion->name)
                ->options(RubricLevel::orderBy('order')->pluck('label', 'id'))
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

## Cálculo del nivel consolidado de una evaluación

Insumo que Tracking va a consumir después (progreso, boletín) — este hito solo
construye el cálculo, no lo muestra en ningún reporte todavía.

```php
// Moda de los niveles de los EvaluationResult de una Evaluation.
// Empate → gana el nivel más bajo (criterio conservador).
public function consolidatedLevel(): ?RubricLevel
{
    $counts = $this->results()
        ->selectRaw('rubric_level_id, COUNT(*) as total')
        ->groupBy('rubric_level_id')
        ->pluck('total', 'rubric_level_id');

    if ($counts->isEmpty()) {
        return null;
    }

    $maxCount = $counts->max();
    $tiedLevelIds = $counts->filter(fn ($count) => $count === $maxCount)->keys();

    return RubricLevel::whereIn('id', $tiedLevelIds)->orderBy('order')->first();
}
```

## Presentación para padres (lenguaje humano)

```php
// En el dashboard del padre, traducir a frases legibles a partir del label del catálogo
$levelText = match($rubricLevel->key) {
    'inicio'          => 'Necesita refuerzo en este tema',
    'en_proceso'      => 'Está en camino, puede mejorar',
    'logro_esperado'  => 'Cumplió con lo esperado',
    'logro_destacado' => 'Fue más allá de lo esperado',
};
```
