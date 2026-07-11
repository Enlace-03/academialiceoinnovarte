---
name: livewire-components
description: Cargar esta skill cuando se construyan componentes Livewire para el panel del estudiante o del padre. Cubre estructura, layouts, componentes compartidos y reglas de UX.
---

# Livewire Components — Panel Estudiante y Padre

## Cuándo usar Livewire vs Filament

| Livewire + Blade | Filament |
|---|---|
| Pantallas del estudiante | Pantallas del admin |
| Pantallas del padre | Pantallas del rector |
| Avatar dock (todos los roles) | Pantallas del profesor |

## Estructura de archivos

```
app/Livewire/
├── Student/
│   ├── Dashboard.php          # Vista principal del estudiante
│   ├── ProjectView.php        # Ver un proyecto ABP y sus fases
│   ├── GuideReader.php        # Leer una guía
│   ├── SubmissionForm.php     # Subir una evidencia
│   ├── ForumThread.php        # Foro de un proyecto
│   └── ProgressView.php      # Mi avance detallado
├── Parent/
│   ├── Dashboard.php          # Vista principal del padre
│   └── ChildProgress.php     # Avance detallado del hijo
└── Shared/
    ├── AvatarDock.php         # Dock de los 4 avatares (global)
    ├── ChatBox.php            # Chat del grupo
    └── NotificationBell.php   # Campana de notificaciones

resources/views/livewire/
├── student/
├── parent/
└── shared/

resources/views/layouts/
├── student.blade.php          # Layout estudiante (colorido, avatar visible)
└── parent.blade.php           # Layout padre (simple, limpio)

resources/views/components/
├── rubric-badge.blade.php     # <x-rubric-badge :level="$level" />
├── progress-bar.blade.php     # <x-progress-bar :value="$pct" />
└── avatar-message.blade.php   # <x-avatar-message :message="$msg" />
```

## Reglas de componentes Livewire

```php
// ✅ CORRECTO: componente llama a Action
class SubmissionForm extends Component
{
    public function submit(): void
    {
        $this->authorize('create', Submission::class);
        app(CreateSubmissionAction::class)->execute(auth()->user(), $this->data);
        $this->dispatch('submission-created');
    }
}

// ❌ INCORRECTO: lógica de negocio en el componente
class SubmissionForm extends Component
{
    public function submit(): void
    {
        Submission::create([...]); // NO
        event(new SubmissionCreated(...)); // NO aquí
    }
}
```

## Datos: siempre de métricas precalculadas

```php
// ✅ CORRECTO: leer de student_metrics (rápido)
public function mount(): void
{
    $this->metrics = StudentMetric::where('student_id', auth()->id())
        ->where('project_id', $this->projectId)
        ->first();
}

// ❌ INCORRECTO: calcular en tiempo real desde learning_events (lento)
public function mount(): void
{
    $this->progress = LearningEvent::where('student_id', auth()->id())->count(); // NO
}
```

## Componente RubricBadge

```blade
{{-- resources/views/components/rubric-badge.blade.php --}}
@props(['level'])

@php
$config = match($level) {
    'not_achieved'       => ['text' => 'No alcanzó',           'class' => 'bg-red-100 text-red-700'],
    'partially_achieved' => ['text' => 'Alcanzó medianamente', 'class' => 'bg-yellow-100 text-yellow-700'],
    'achieved'           => ['text' => 'Alcanzó',              'class' => 'bg-green-100 text-green-700'],
    'exceeded'           => ['text' => 'Superó',               'class' => 'bg-blue-100 text-blue-700'],
    default              => ['text' => 'Sin evaluar',          'class' => 'bg-gray-100 text-gray-500'],
};
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ' . $config['class']]) }}>
    {{ $config['text'] }}
</span>
```

## Componente ProgressBar

```blade
{{-- resources/views/components/progress-bar.blade.php --}}
@props(['value' => 0, 'label' => true])

@php
$color = match(true) {
    $value >= 80 => 'bg-green-500',
    $value >= 50 => 'bg-yellow-500',
    default      => 'bg-red-400',
};
@endphp

<div {{ $attributes }}>
    @if($label)
        <div class="flex justify-between text-xs text-gray-500 mb-1">
            <span>Avance</span>
            <span>{{ $value }}%</span>
        </div>
    @endif
    <div class="w-full bg-gray-200 rounded-full h-3">
        <div class="{{ $color }} h-3 rounded-full transition-all duration-500"
             style="width: {{ $value }}%"
             role="progressbar"
             aria-valuenow="{{ $value }}"
             aria-valuemin="0"
             aria-valuemax="100">
        </div>
    </div>
</div>
```

## AvatarDock: lógica de selección

```php
class AvatarDock extends Component
{
    public ?array $currentMessage = null;

    public function mount(): void
    {
        $user = auth()->user();
        $route = request()->route()->getName();
        $role = $user->getRoleNames()->first();

        // Determinar avatar según rol y grado
        $avatarKey = $this->resolveAvatarKey($user, $role);

        $this->currentMessage = AvatarMessage::where('avatar_key', $avatarKey)
            ->where('context_route', $route)
            ->where('target_role', $role)
            ->where('is_active', true)
            ->orderBy('priority')
            ->first()
            ?->toArray();
    }

    private function resolveAvatarKey(User $user, string $role): string
    {
        if ($role === 'student' && $user->studentProfile?->school_grade?->level <= 5) {
            return 'mentor_nino';
        }
        if ($role === 'student') {
            return 'mentora_mujer';
        }
        if ($role === 'parent') {
            return 'docente_guia';
        }
        return 'rectora_isabel'; // onboarding general
    }
}
```

## UX: reglas para primaria vs secundaria

| Aspecto | Primaria (1°-5°) | Secundaria (6°-9°) |
|---|---|---|
| Avatar | Visible y grande | Discreto, esquina |
| Métricas | Solo barra visual, sin % | Sí mostrar % |
| Lenguaje | "¡Buen trabajo!" | Más neutro |
| Opciones por pantalla | Máx 3 | Máx 5 |
| Rúbrica | Solo color e ícono | Texto completo |

## Rutas del panel estudiante y padre

```php
// routes/web.php
Route::middleware(['auth', 'role:student'])->group(function () {
    Route::get('/', [StudentDashboardController::class, 'index'])->name('student.dashboard');
    Route::get('/proyectos/{project:uuid}', ...)->name('student.project.show');
    // ...
});

Route::middleware(['auth', 'role:parent'])->prefix('familia')->group(function () {
    Route::get('/', [ParentDashboardController::class, 'index'])->name('parent.dashboard');
    // ...
});
```
