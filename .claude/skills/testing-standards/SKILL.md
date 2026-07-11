---
name: testing-standards
description: Cargar esta skill cuando se escriban tests. Cubre qué testear, cómo usar Pest con Laravel, y las prioridades de testing para este proyecto.
---

# Testing — Liceo Innovarte

## Stack de testing
- **Pest** con plugins de Laravel (`pestphp/pest-plugin-laravel`)
- `RefreshDatabase` en todos los tests que tocan BD
- Factories para datos de prueba
- **No** Mockery para modelos — usar factories reales

## Qué testear (priorizado)

| Prioridad | Qué | Por qué |
|---|---|---|
| 🔴 Alta | Actions críticas | Son el corazón del negocio |
| 🔴 Alta | Policies de autorización | Datos de menores — no puede haber brechas |
| 🟡 Media | Filament Resources | Smoke test: tabla carga, formulario guarda |
| 🟡 Media | Livewire Components | Flujo del estudiante end-to-end |
| 🟢 Baja | Models y relaciones | Solo si tienen lógica compleja |

## Qué NO testear
- Getters/setters triviales
- Migraciones
- Configuración de Filament
- Código de terceros (Spatie, Filament)

## Estructura de un test de Action

```php
// tests/Feature/Assessment/EvaluateSubmissionActionTest.php
use App\Models\User;
use App\Modules\Assessment\Actions\EvaluateSubmissionAction;
use App\Modules\Assessment\Models\Submission;
use App\Modules\Shared\Enums\RubricLevel;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->teacher = User::factory()->create()->assignRole('teacher');
    $this->student = User::factory()->create()->assignRole('student');
    $this->submission = Submission::factory()
        ->for($this->student, 'student')
        ->create(['status' => 'submitted']);
});

it('evalúa una entrega correctamente', function () {
    $criteriaResults = [
        1 => RubricLevel::Achieved->value,
        2 => RubricLevel::Exceeded->value,
    ];

    $evaluation = app(EvaluateSubmissionAction::class)
        ->execute($this->teacher, $this->submission, $criteriaResults, 'Buen trabajo');

    expect($evaluation)->not->toBeNull()
        ->and($this->submission->fresh()->status)->toBe('evaluated')
        ->and($evaluation->results)->toHaveCount(2);
});

it('no permite evaluar una entrega ya evaluada', function () {
    // Evaluar primera vez
    app(EvaluateSubmissionAction::class)
        ->execute($this->teacher, $this->submission, [1 => 'achieved']);

    // Intentar evaluar de nuevo → debe lanzar excepción
    expect(fn () => app(EvaluateSubmissionAction::class)
        ->execute($this->teacher, $this->submission, [1 => 'achieved'])
    )->toThrow(\Illuminate\Http\Exceptions\HttpResponseException::class);
});

it('un estudiante no puede evaluar entregas', function () {
    expect(fn () => app(EvaluateSubmissionAction::class)
        ->execute($this->student, $this->submission, [1 => 'achieved'])
    )->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});
```

## Estructura de un test de Policy

```php
// tests/Feature/Identity/StudentPolicyTest.php
uses(RefreshDatabase::class);

it('un rector puede crear cualquier estudiante', function () {
    $rector = User::factory()->create()->assignRole('rector');
    expect($rector->can('create', [User::class, null]))->toBeTrue();
});

it('un profesor con permiso acotado solo puede matricular en sus grupos', function () {
    $teacher = User::factory()->create()->assignRole('teacher');
    $allowedGroup = Group::factory()->create();
    $otherGroup = Group::factory()->create();

    // Conceder permiso con alcance al grupo permitido
    UserGrant::create([
        'user_id'    => $teacher->id,
        'granted_by' => User::factory()->create()->assignRole('rector')->id,
        'permission' => 'students.create.scoped',
        'scope'      => ['type' => 'groups', 'group_ids' => [$allowedGroup->id]],
    ]);
    $teacher->givePermissionTo('students.create.scoped');

    expect($teacher->can('create', [User::class, $allowedGroup->id]))->toBeTrue()
        ->and($teacher->can('create', [User::class, $otherGroup->id]))->toBeFalse();
});
```

## Factories importantes

```php
// Crear usuario con rol en una línea
User::factory()->create()->assignRole('teacher');

// Crear proyecto con fases
Project::factory()
    ->has(Phase::factory()->count(4))
    ->for(Group::factory())
    ->create();

// Crear submission evaluada
Submission::factory()
    ->has(Evaluation::factory()->has(EvaluationResult::factory()->count(3)))
    ->create(['status' => 'evaluated']);
```

## Correr tests

```bash
php artisan test                          # todos
php artisan test --filter=EvaluateSubmission  # uno específico
php artisan test tests/Feature/Assessment/    # una carpeta
php artisan test --coverage               # con cobertura (requiere Xdebug)
```
