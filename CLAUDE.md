# CLAUDE.md — Plataforma Liceo Innovarte

Contexto del proyecto para Claude Code. Lee esto antes de cualquier tarea.

## Qué es este proyecto

Plataforma educativa para Liceo Innovarte (Colombia): colegio semipresencial con modelo de
**Aprendizaje Basado en Proyectos (ABP)**, 1° a 9° grado, ~200 estudiantes, 3 docentes.
NO es un LMS tradicional de cursos/lecciones/exámenes.

## Stack

- **Laravel 12** + **PHP 8.3**
- **Livewire 3** (viene con Filament)
- **Filament 4** (paneles admin y académico)
- **Tailwind CSS** (v4, viene con Filament)
- **Alpine.js** (viene con Livewire)
- **MySQL 8** (hosting cPanel)
- Sin Vue, sin Inertia, sin Sanctum (hasta la app móvil)

## Restricciones del hosting (cPanel, sin SSH)

- `QUEUE_CONNECTION=database` — nunca sugerir Redis
- `CACHE_STORE=database` — ni Memcached
- Todo lo programado corre vía `schedule:run` en cron de cPanel (cada minuto)
- `queue:work --stop-when-empty --max-time=50` — nunca workers permanentes
- Deploy: rama `deploy` con `vendor/` y `public/build/` comiteados
- No supervisord, no Horizon, no daemons
- 4 GB RAM compartida — jobs deben procesar en `chunk(50)`

## Arquitectura: Filament + Modules + Livewire

### Tres capas de UI

1. **Filament Resources** (paneles admin y académico): CRUDs, tablas, formularios,
   dashboards. Para admin, rectora y profesores.
2. **Livewire Components** (pantallas custom): Panel del estudiante y del padre.
   UI distinta, no admin-like. Vistas en `resources/views/livewire/`.
3. **Modules** (dominio puro, sin UI): Models, Actions, Events, Policies, Jobs.
   Viven en `app/Modules/{Contexto}/`. Son llamados tanto por Filament como por Livewire.

### Paneles Filament

| Panel | Ruta | Roles | PanelProvider |
|---|---|---|---|
| Admin | `/admin` | admin | `AdminPanelProvider` |
| Académico | `/academia` | rector, teacher | `AcademicPanelProvider` |

Estudiantes y padres NO usan Filament; tienen sus propias rutas web con Livewire.

### Reglas de arquitectura (no negociables)

1. **Filament Resources y Livewire Components no contienen lógica de negocio.**
   Llaman a Actions del módulo correspondiente. Filament Actions (botones de tabla)
   delegan en Module Actions. No confundir los dos.
2. **Una Action de módulo = un caso de uso**, con `execute()`.
   Ej: `App\Modules\Assessment\Actions\EvaluateSubmissionAction`.
3. **Todo hecho significativo dispara un evento de dominio.**
   Ej: `SubmissionEvaluated`, `GuideCompleted`, `ForumPostCreated`.
   Los listeners de Tracking/Prediction/Communication reaccionan.
4. **Todo evento de aprendizaje se registra en `learning_events`** vía listener.
   Nunca escribir en esa tabla directamente.
5. **Models delgados**: relaciones, casts, scopes, accessors. Nada más.
6. **Policies para toda autorización.** Filament las respeta automáticamente si
   están registradas. Nunca chequear roles con strings.
7. **UUIDs públicos** en URLs para todo recurso de estudiantes. Filament usa
   IDs en el admin (está protegido por auth); las rutas públicas/Livewire usan uuid.

## Dominio: conceptos clave

- **Proyecto ABP** (`projects`): unidad central de trabajo, 3 o 6 meses,
  puede vincular varias materias (pivote `project_subject`).
- **Fases/Hitos** (`phases`): etapas ordenadas. En Filament: nested resource.
- **Guías** (`guides`): contenido pre-creado. El docente NO crea cursos.
- **Recursos** (`resources`): archivos/enlaces complementarios del docente.
- **Evidencias esperadas** (`expected_evidences`): qué debe entregar el estudiante.
- **Entregas** (`submissions`): lo que sube el estudiante. En Filament Academic:
  tabla con acción de evaluación rápida (estilo SpeedGrader).
- **Evaluación cualitativa**: rúbricas de 4 niveles. Enum `RubricLevel`:
  `not_achieved(1) | partially_achieved(2) | achieved(3) | exceeded(4)`.
  El valor numérico JAMÁS se muestra. En Filament usar `TextColumn::badge()`
  con colores: danger/warning/success/info. En Livewire usar el componente
  `<x-rubric-badge />`.
- **Barra de avance** (`student_progress.progress_pct`): 0-100%.
  Fórmula: guías (50%) + foros (20%) + chats (10%) + calidad evaluaciones (20%).
  Pesos en `config/tracking.php`.
- **Alertas de riesgo** (`risk_alerts`): reglas heurísticas, con `reason` legible.
- **Avatares**: 4 personajes fijos:
  `rectora_isabel`, `docente_guia`, `mentor_nino`, `mentora_mujer`.
  Mensajes scripted en `avatar_messages`, sin IA generativa en MVP.

## Roles y permisos

Modelo de **permisos granulares delegables** (ver docs/permissions-model.md y la skill
`permissions-conventions`). NO es un sistema de 5 roles rígidos.

- **Personal** (admin, rector, coordinador, secretario, teacher): permisos atómicos
  marcables individualmente. Los roles-preset solo precargan casillas.
- **Estudiantes y padres**: roles fijos uniformes (`student`, `parent`), sin configuración
  por permiso. Acceso por middleware de ruta y scoping a `auth()->id()`.

Reglas clave:
- `users.create` y `users.grant` son permisos (no roles). Diego e Isa los tienen; pueden
  concederlos a un coordinador/secretario futuro.
- **Techo de delegación estricto**: nadie otorga un permiso que no tiene. Validar SIEMPRE
  en el servidor (`AssignPermissionsAction`), nunca solo en la UI.
- **Permisos con alcance**: `students.create.scoped` lleva scope en `user_grants`
  (`all` o `groups`). El rector define el alcance al conceder. Un profesor solo matricula
  si el rector le dio este permiso.
- Fuente de verdad: `config/permissions.php`. No hardcodear nombres de permisos fuera de ahí.
- No self-signup: cada cuenta la crea alguien con `users.create`.
- Autorización siempre por Policy, respetada automáticamente por Filament.

## Filament: convenciones específicas

- Resources en `app/Filament/{Panel}/Resources/`.
- Widgets en `app/Filament/{Panel}/Widgets/`.
- Custom pages en `app/Filament/{Panel}/Pages/`.
- Formularios con `TextInput::make()->label('Título')` — labels en español.
- Tablas con columnas descriptivas, badges para estados, filtros útiles.
- Widgets de dashboard: `StatsOverviewWidget` para KPIs,
  `ChartWidget` (ApexCharts nativo de Filament) para gráficas.
- Nested resources para proyecto → fases.
- `SubmissionResource` con acción custom `EvaluateAction` que abre modal
  con formulario de rúbrica (no página nueva).
- Filament Notifications para alertas in-app a profesores y rectora.

## Livewire: convenciones específicas

- Componentes en `app/Livewire/{Student,Parent,Shared}/`.
- Vistas en `resources/views/livewire/{student,parent,shared}/`.
- Layout del estudiante: `resources/views/layouts/student.blade.php`.
- Layout del padre: `resources/views/layouts/parent.blade.php`.
- Rutas en `routes/web.php`, protegidas con middleware `auth` + `role:student`
  o `role:parent`. No usar Filament para estas rutas.
- Alpine.js para interactividad ligera (toggles, animaciones del avatar).
- Gráficas con ApexCharts vía CDN o componente Blade que recibe datos por props.

## MySQL: particularidades

- `learning_events` particionada → SIN foreign keys, PK compuesta `(id, occurred_at)`.
- Columnas JSON (no jsonb): usar casts `array` o `AsCollection` en modelos.
- Timestamps en UTC en BD; conversión a `America/Bogota` solo en presentación.
- Índices compuestos definidos explícitamente en migraciones.

## Qué NO hacer

- No agregar features fuera del MVP sin preguntar.
- No crear calificaciones numéricas visibles. Este colegio no las usa.
- No enviar datos de estudiantes a servicios externos.
- No usar `env()` fuera de archivos config.
- No poner lógica de negocio en Filament Resources ni en Livewire Components.
- No crear una API REST (no hay frontend separado; cuando llegue la app móvil se agrega).
- No usar Filament para las pantallas de estudiante o padre.

## Comandos frecuentes

```bash
php artisan migrate:fresh --seed
php artisan test
php artisan filament:assets              # regenerar assets de Filament
php artisan metrics:recalculate
php artisan risk:evaluate
```
