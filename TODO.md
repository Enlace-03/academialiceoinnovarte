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

**Estado:** ✅ resuelto — `document_number` e `is_active` agregadas al atributo `#[Fillable(...)]` de `app/Models/User.php`.

## 4. `AssignPermissionsAction` y `CreateStaffUserAction` — código muerto/prototipo con imports rotos

**Estado:** confirmado sin punto de entrada activo — no rompe nada en producción hoy.

**Contexto:** ambas en `app/Modules/Identity/Actions/`. Ninguna está referenciada desde ningún Filament Resource, Livewire component ni ruta — el único rastro de uso es un ejemplo en docblock (`CreateStaffUserAction.php`, comentado, no invocación real). Las dos importan `App\Modules\Identity\Models\User`, namespace que no existe (el modelo real es `App\Models\User`). Además, `AssignPermissionsAction` depende de `App\Modules\Identity\Models\UserGrant`, que tampoco existe (ver punto 5). Es la misma causa raíz que tenía `StudentPolicy::create()`, ya simplificada.

**Cuándo retomarlo:** evaluar si se completan (corrigiendo el namespace y creando `UserGrant`) o se eliminan, cuando se diseñe el flujo real de creación de personal con permisos delegados por alcance.

## 5. Migración `user_grants` huérfana (sin modelo Eloquent ni consumidor activo)

**Estado:** tabla migrada (`2027_01_01_000070_create_user_grants_table.php`), sin modelo ni uso real.

**Contexto:** quedó huérfana tras simplificar `StudentPolicy::create()` (que era su único consumidor de lectura, vía un modelo `UserGrant` que nunca se creó). Pensada para permisos con alcance (ej. `students.create.scoped` limitado a ciertos grupos), complementando a Spatie.

**Cuándo retomarlo:** decidir si se elimina la tabla o se retoma como mecanismo de delegación con alcance más adelante — evaluando primero si el techo de delegación existente (`HasDelegationCeiling`, permisos completos sin scope por grupo) ya cubre el caso de uso real antes de resucitar un segundo sistema paralelo.

## 5. Relation Manager espejo del lado acudiente ("Estudiantes a cargo")

**Estado:** no implementado — alcance explícitamente diferido.

**Contexto:** las relaciones `User::children()` y `User::guardians()` ya existen y están probadas (32/32 tests); solo falta construir el Relation Manager equivalente en el Resource del acudiente (`GuardiansRelationManager` hoy solo vive del lado del Resource de estudiante).

**Cuándo retomarlo:** cuando el flujo de trabajo de secretaría requiera registrar primero al acudiente y asignarle estudiantes después, en vez de siempre partir del estudiante.

## 6. Historización de matrícula por año lectivo

**Estado:** evolución futura, decidida explícitamente como fuera de alcance.

**Contexto:** hoy `User::group_id` es una FK simple (Opción A) — un estudiante tiene un grupo, sin historial. Si se necesita trazabilidad de "en qué grupo estuvo el estudiante X en el año Y" (boletines históricos, procesos de promoción automática de año), hay que migrar a una tabla de matrícula (`student_enrollments`: `student_id`, `group_id`, `school_year`, `status`), donde el grupo "actual" sea la fila activa.

**Cuándo retomarlo:** cuando se construya el proceso formal de promoción/cierre de año lectivo.

## 7. Consentimiento de tratamiento de datos: solo cubre el camino de la UI de Filament

**Estado:** riesgo conocido, aceptado por ahora — no bloquea la Opción A (confirmación administrativa vía `GuardiansRelationManager`).

**Contexto:** `RecordDataTreatmentConsentAction` (que crea el `ParentStudent` y el `DataTreatmentConsent` en la misma transacción) solo se invoca desde el Attach action de `GuardiansRelationManager`. Si en el futuro se crea un `parent_student` por otro camino (seeder, carga masiva por Excel/CSV, `tinker`), no queda consentimiento registrado — y nada a nivel de base de datos lo impide, porque `data_treatment_consents` no tiene una constraint que dependa de la existencia de una fila en `parent_student`. Es la limitación típica de "checkbox en formulario" vs. validación real de dominio.

**Cuándo retomarlo:** obligatorio reforzar (ej. mover la validación a un observer/listener del propio `parent_student`, o exigir el consentimiento antes de cualquier inserción, no solo desde Filament) si se automatiza la carga masiva de estudiantes/acudientes.

---

## Notas de infraestructura (resueltas)

- **`.env.testing`:** creado con `DB_DATABASE=liceo_innovarte_testing` explícito, para que `--env=testing` invocado manualmente no caiga por fallback a la base de desarrollo (`liceo_innovarte`), como ocurrió por accidente durante trabajo previo.
