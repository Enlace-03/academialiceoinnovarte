# TODO — Pendientes técnicos

Backlog centralizado de deuda técnica y trabajo diferido conscientemente. Cada entrada indica su estado, el contexto de la decisión, y la condición que justifica retomarla — no son tareas urgentes, son decisiones documentadas.

## 1. Redirect cross-panel

**Estado:** no implementado, decisión explícita de dejarlo así por ahora.

**Contexto:** hoy, un usuario ya autenticado que navega al panel equivocado (ej. un `student` entrando a `/admin`) recibe 403 plano del middleware `Authenticate` de Filament. La alternativa evaluada era un middleware propio (`RedirectIfCannotAccessPanel extends Authenticate`) que redirija al panel correcto si el usuario tiene acceso a otro, registrado en `->authMiddleware([...])` del `AdminPanelProvider`.

**Cuándo retomarlo:** si el 403 genera fricción real en soporte/uso diario.

## 2. Auditoría de autorización por recurso en `/academia`

**Estado:** pendiente, no urgente.

**Contexto:** `canAccessPanel()` para el panel `academic` es deliberadamente abierto (cualquier usuario con al menos un rol asignado entra) — la restricción real recae en Policies por recurso. Hoy `/academia` no tiene recursos propios, así que no hay superficie que auditar todavía.

**Cuándo retomarlo:** obligatorio antes de dar por completo cualquier Resource/Page/Widget nuevo que se construya dentro de `/academia` — cada uno necesita su propia autorización explícita, no heredar el acceso abierto del panel.

## 3. Columnas faltantes en `User::$fillable` (vía `#[Fillable(...)]`)

**Estado:** deuda preexistente, no introducida por este trabajo, sin tocar.

**Contexto:** `document_number` e `is_active` no están incluidas en el atributo `#[Fillable(...)]` de `app/Models/User.php` — mismo problema que tenía `group_id` antes de corregirse.

**Cuándo retomarlo:** cuando algún formulario/acción necesite escribir esas columnas por asignación masiva y falle silenciosamente por no estar en la lista.

## 4. `StudentPolicy::create()` referencia un modelo inexistente (`UserGrant`)

**Estado:** bug latente confirmado, sin punto de entrada activo hoy — no rompe nada en producción actualmente.

**Contexto:** `app/Modules/Identity/Policies/StudentPolicy.php`, método `create()`, importa `App\Modules\Identity\Models\UserGrant`, que no existe en ningún lugar del codebase. No está registrada en `Gate::policy()` (ese slot lo ocupa `UserPolicy::class` para `User::class`), y ningún Resource/Action/Page la invoca hoy — el único uso real es en el test `ParentStudentRelationTest.php` (invocación manual) y una mención en docblock de `CreateStaffUserAction.php` que es solo ejemplo, no llamada real.

**Cuándo retomarlo:** obligatorio resolver antes de conectar cualquier flujo real de "crear estudiante" que dependa de esta Policy — de lo contrario falla en cuanto se invoque.

## 5. Relation Manager espejo del lado acudiente ("Estudiantes a cargo")

**Estado:** no implementado — alcance explícitamente diferido.

**Contexto:** las relaciones `User::children()` y `User::guardians()` ya existen y están probadas (32/32 tests); solo falta construir el Relation Manager equivalente en el Resource del acudiente (`GuardiansRelationManager` hoy solo vive del lado del Resource de estudiante).

**Cuándo retomarlo:** cuando el flujo de trabajo de secretaría requiera registrar primero al acudiente y asignarle estudiantes después, en vez de siempre partir del estudiante.

## 6. Historización de matrícula por año lectivo

**Estado:** evolución futura, decidida explícitamente como fuera de alcance.

**Contexto:** hoy `User::group_id` es una FK simple (Opción A) — un estudiante tiene un grupo, sin historial. Si se necesita trazabilidad de "en qué grupo estuvo el estudiante X en el año Y" (boletines históricos, procesos de promoción automática de año), hay que migrar a una tabla de matrícula (`student_enrollments`: `student_id`, `group_id`, `school_year`, `status`), donde el grupo "actual" sea la fila activa.

**Cuándo retomarlo:** cuando se construya el proceso formal de promoción/cierre de año lectivo.

---

## Notas de infraestructura (resueltas)

- **`.env.testing`:** creado con `DB_DATABASE=liceo_innovarte_testing` explícito, para que `--env=testing` invocado manualmente no caiga por fallback a la base de desarrollo (`liceo_innovarte`), como ocurrió por accidente durante trabajo previo.
