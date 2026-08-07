---
name: filament-conventions
description: Use this skill whenever creating or modifying Filament Resources, Pages, Widgets, or Actions in the Liceo Innovarte project. Ensures consistent patterns across both the Admin and Academic panels, and enforces the separation between Filament UI and domain logic in Modules.
---

# Convenciones Filament — Liceo Innovarte

## Regla #1: Filament es UI, Modules es lógica

```php
// ✅ CORRECTO: Filament Action delega en Module Action
Tables\Actions\Action::make('evaluate')
    ->form([...])
    ->action(function (Submission $record, array $data) {
        app(EvaluateSubmissionAction::class)->execute($record, $data);
    });

// ❌ INCORRECTO: lógica de negocio directa en Filament
Tables\Actions\Action::make('evaluate')
    ->action(function (Submission $record, array $data) {
        $evaluation = Evaluation::create([...]);  // NO
        event(new SubmissionEvaluated(...));       // NO aquí
    });
```

## Estructura de un Resource

```php
// app/Filament/Academic/Resources/ProjectResource.php
class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationGroup = 'ABP';
    protected static ?string $modelLabel = 'Proyecto';
    protected static ?string $pluralModelLabel = 'Proyectos';

    // Formulario
    public static function form(Form $form): Form { ... }

    // Tabla
    public static function table(Table $table): Table { ... }

    // Relaciones visibles en la vista de detalle
    public static function getRelations(): array
    {
        return [
            PhasesRelationManager::class,
        ];
    }
}
```

## Labels y textos siempre en español

```php
TextInput::make('title')->label('Título')->required(),
Select::make('status')->label('Estado')
    ->options([
        'draft' => 'Borrador',
        'active' => 'Activo',
        'finished' => 'Finalizado',
    ]),
```

## Rúbricas: cómo mostrar los 4 niveles

Los niveles viven en la tabla catálogo `rubric_levels` (global, sin `institution_id`),
no como ENUM de base de datos — así se puede agregar un quinto nivel sin migración.
Escala vigente (Hito 2, confirmada): **Inicio → En proceso → Logro esperado → Logro
destacado**. Ningún otro set de etiquetas anterior sigue vigente.

```php
// En tablas: rubric_level_id es la FK real; el label/color vienen del catálogo.
TextColumn::make('rubricLevel.label')
    ->label('Nivel')
    ->badge()
    ->color(fn (RubricLevel $state) => $state->color), // color hex ya viene del catálogo

// Catálogo sembrado por RubricLevelSeeder:
// key                => label               | color   | order
// 'inicio'           => 'Inicio'             | rojo    | 1
// 'en_proceso'       => 'En proceso'         | naranja | 2
// 'logro_esperado'   => 'Logro esperado'     | amarillo| 3
// 'logro_destacado'  => 'Logro destacado'    | verde   | 4

// NUNCA mostrar el valor numérico (order) en ninguna columna ni formulario.
```

## Alertas de riesgo: widget estilo triage

```php
// Widget en el dashboard académico: lista priorizada de estudiantes en riesgo
class StudentsAtRiskWidget extends Widget
{
    // Ordenar por level (critical > high > medium > low)
    // Mostrar: nombre del estudiante, proyecto, razón, días inactivo
    // Acción: "Ver perfil" → StudentProfile page
}
```

## Nested Resources (proyecto → fases)

Filament 4 los soporta nativamente. Crear PhaseResource como nested de ProjectResource.
Las fases se editan en el contexto de su proyecto padre, no como recurso independiente.

## Gráficas en Widgets

Filament incluye `ChartWidget` basado en ApexCharts:

```php
class GroupProgressWidget extends ChartWidget
{
    protected static ?string $heading = 'Avance del grupo';

    protected function getData(): array
    {
        // Leer de student_metrics, NUNCA de learning_events directo
        return [
            'datasets' => [...],
            'labels' => [...],
        ];
    }

    protected function getType(): string
    {
        return 'bar'; // bar | line | doughnut | radar | ...
    }
}
```

## Qué NO poner en Filament

- Pantallas de estudiante o padre → Livewire Components.
- Lógica de cálculo de métricas → Modules/Tracking/Actions.
- Reglas de riesgo → Modules/Prediction/Rules.
- Envío de notificaciones → Modules/Communication/Listeners.
