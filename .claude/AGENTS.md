# AGENTS.md — Sub-agentes de Claude Code

Define agentes especializados para tareas recurrentes del proyecto.
Cada agente tiene su contexto pre-cargado — no repetir instrucciones en cada tarea.

---

## agente: filament-builder
**Cuándo usarlo:** crear o modificar Resources, Widgets, Pages, Actions de Filament.

**Contexto pre-cargado:**
- Lee skill `filament-conventions` antes de empezar
- Panel Admin → `app/Filament/Admin/` → roles: admin
- Panel Académico → `app/Filament/Academic/` → roles: rector, teacher
- Labels y mensajes siempre en español
- Lógica de negocio → delegar a Module Action, nunca en el Resource
- Rúbricas → usar badge con colores, nunca mostrar valor numérico

**Prompt tipo:**
```
@filament-builder Crea el ProjectResource en el panel académico con:
- Tabla: título, grupo, duración, estado (badge), fecha inicio
- Formulario: título, descripción, grupo (select), duración (3 o 6 meses), materias (checkboxes)
- Acción: "Activar proyecto" → delegar en ActivateProjectAction
- Test Pest incluido
```

---

## agente: domain-builder
**Cuándo usarlo:** crear Models, Actions, Events, Policies, Jobs en app/Modules/.

**Contexto pre-cargado:**
- Lee skill `module-generator` antes de empezar
- Lee skill `abp-domain` para entender las entidades del proyecto
- Una Action = un caso de uso, método `execute()`
- Todo hecho significativo → disparar evento de dominio al final del execute()
- Models delgados: solo relaciones, casts, scopes
- Policies para toda autorización

**Prompt tipo:**
```
@domain-builder Crea CreateProjectAction en Modules/Project/Actions/.
Recibe: group_id, title, description, duration_months, subject_ids[].
Crea el proyecto, vincula materias, dispara ProjectCreated event.
Incluye ProjectPolicy con método create().
Test Pest del caso feliz + autorización.
```

---

## agente: livewire-builder
**Cuándo usarlo:** crear componentes Livewire para el panel estudiante o padre.

**Contexto pre-cargado:**
- Lee skill `livewire-components` antes de empezar
- Estudiante → `app/Livewire/Student/` + `resources/views/livewire/student/`
- Padre → `app/Livewire/Parent/` + `resources/views/livewire/parent/`
- Layout estudiante: `resources/views/layouts/student.blade.php`
- Layout padre: `resources/views/layouts/parent.blade.php`
- NO usar Filament para estas pantallas
- Barra de avance → componente `<x-progress-bar :value="$progress" />`
- Niveles de rúbrica → componente `<x-rubric-badge :level="$level" />`
- Alpine.js para interactividad ligera

**Prompt tipo:**
```
@livewire-builder Crea el componente Student/Dashboard.php con su vista.
Muestra: próxima evidencia por entregar, barra de avance del proyecto activo,
últimas 3 evaluaciones con badge de rúbrica, mensaje del avatar contextual.
Datos de student_metrics, no de learning_events directo.
```

---

## agente: migration-writer
**Cuándo usarlo:** crear o modificar migraciones de base de datos.

**Contexto pre-cargado:**
- Lee skill `migration-conventions` antes de empezar
- MySQL 9.1 en WampServer local → strings en índices con longitud explícita ≤ 100
- `learning_events` → sin foreign keys, PK compuesta, sin modificar con Blueprint
- Siempre `down()` funcional
- Soft deletes en entidades académicas
- UUIDs en recursos que aparecen en URLs

**Prompt tipo:**
```
@migration-writer Crea la migración para la tabla notifications.
Campos: user_id, type(50), data(json), read_at(nullable timestamp).
Índice en user_id + read_at.
```

---

## agente: test-writer
**Cuándo usarlo:** escribir tests Pest para Actions, Resources o componentes Livewire.

**Contexto pre-cargado:**
- Lee skill `testing-standards` antes de empezar
- Pest con helpers de Laravel (RefreshDatabase, actingAs)
- Prioridad: Actions críticas > Policies > Filament Resources > Livewire
- Un test = un comportamiento, no un método
- Mocks solo para servicios externos

**Prompt tipo:**
```
@test-writer Escribe los tests Pest para EvaluateSubmissionAction.
Casos: evaluación exitosa, estudiante sin entrega, profesor sin permiso,
nivel de rúbrica inválido. Usar factories.
```

---

## agente: seeder-builder
**Cuándo usarlo:** crear seeders de datos de prueba realistas.

**Contexto pre-cargado:**
- Datos en español, coherentes con el dominio de Liceo Innovarte
- 200 estudiantes, 3 docentes, 1 rectora, 1 admin
- 9 grados (1°-9°), 2 grupos por grado
- 17 materias (9 tradicionales + 8 innovadoras)
- 1 proyecto ABP activo de ejemplo con 4 fases y guías
- Roles asignados: admin→Diego, rector→Isa, teacher→3 docentes

**Prompt tipo:**
```
@seeder-builder Crea el InstitutionSeeder con la institución Liceo Innovarte,
los 9 grados escolares, 2 grupos por grado (A y B), y las 17 materias
del catálogo del CLAUDE.md.
```
