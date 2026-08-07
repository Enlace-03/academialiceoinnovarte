# TODO — Pendientes técnicos

Backlog centralizado de deuda técnica y trabajo diferido conscientemente. Cada entrada indica su estado, el contexto de la decisión, y la condición que justifica retomarla — no son tareas urgentes, son decisiones documentadas.

## 1. Redirect cross-panel

**Estado:** no implementado, decisión explícita de dejarlo así por ahora.

**Contexto:** hoy, un usuario ya autenticado que navega al panel equivocado (ej. un `student` entrando a `/admin`) recibe 403 plano del middleware `Authenticate` de Filament. La alternativa evaluada era un middleware propio (`RedirectIfCannotAccessPanel extends Authenticate`) que redirija al panel correcto si el usuario tiene acceso a otro, registrado en `->authMiddleware([...])` del `AdminPanelProvider`.

**Cuándo retomarlo:** si el 403 genera fricción real en soporte/uso diario.

## 2. Auditoría de autorización por recurso en `/academia`

**Estado:** patrón establecido con `ProjectPolicy` (Hito 1C) — aplicar el mismo criterio a cada Resource nuevo en ese panel.

**Contexto:** `canAccessPanel()` para el panel `academic` sigue siendo deliberadamente abierto (cualquier usuario con al menos un rol asignado entra) — la restricción real recae en Policies por recurso. `ProjectResource` (primer Resource real de `/academia`) fija el patrón a replicar: `viewAny()`/`create()`/`update()` distinguiendo `own` (vía `created_by_user_id`) de `all` (permiso `.all`), y las acciones de gestión de sub-recursos (`managePhases`, `manageResources` en `ProjectPolicy`) no son puertas independientes — exigen además pasar la autorización `update()` sobre el registro padre puntual, no solo tener el permiso global. `ProjectResource::getEloquentQuery()` refuerza el filtro `own` también a nivel de listado, no solo de política por registro.

**Cuándo retomarlo:** obligatorio antes de dar por completo cualquier Resource/Page/Widget nuevo que se construya dentro de `/academia` — replicar el mismo criterio (own/all + sub-recursos acoplados a la autorización del padre), no heredar el acceso abierto del panel.

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

## 8. Ocultar roles "más altos" en la asignación de roles/permisos

**Estado:** no implementado, decisión explícita de dejarlo así por ahora.

**Contexto:** hoy `HasDelegationCeiling::assignableRoles()` ya filtra qué roles puede *asignar* un usuario (por subconjunto de permisos), pero no oculta de la vista (tablas, selects de filtro) los roles que están por encima del propio nivel del usuario — ej. un coordinador puede ver que existe el rol "Rector" en la pestaña Personal o en el filtro de rol, aunque no pueda asignarlo. Se evaluaron dos enfoques, ninguno descartado:
  - **Opción estricta por subconjunto de permisos:** reutilizar la misma lógica de `assignableRoles()` (comparación de sets de permisos) también para decidir qué es *visible*, no solo qué es *asignable*.
  - **Opción de nivel numérico simple:** usar la columna `level` de Spatie (ya sembrada por `RoleLevelSeeder`) para ocultar roles con `level` mayor al del usuario actual — más simple de razonar, pero menos preciso que comparar permisos reales.

**Cuándo retomarlo:** cuando haya un caso de uso real donde la visibilidad (no solo la capacidad de asignar) de roles superiores genere fricción o dudas de seguridad percibida por el personal.

## 9. Columnas de la tabla de Usuarios iguales en las tres pestañas (Personal/Estudiantes/Acudientes)

**Estado:** decisión consciente de no complicar el `getTabs()` inicial — las tres pestañas comparten las mismas columnas de `UsersTable`.

**Contexto:** la pestaña "Estudiantes" se beneficiaría de mostrar Grado/Grupo de forma más prominente (ya está como columna, pero es igual de relevante en las otras dos pestañas donde siempre muestra "—"); "Personal" no necesita esa columna en absoluto. Filament permite variar columnas por pestaña, pero eso requeriría separar la definición de columnas de `UsersTable::configure()` y condicionarla al tab activo.

**Cuándo retomarlo:** si el volumen real de columnas por categoría lo justifica (ej. cuando se agreguen columnas específicas de acudientes como "hijos a cargo").

## 10. Inventario de scaffolding de BD pre-creado para módulos futuros (Assessment/Community/Tracking/Prediction/Avatar)

**Estado:** referencia obligatoria antes de especificar cada hito correspondiente — no es deuda técnica, es un mapa para no repetir el conflicto de orden de migraciones del Hito 1C.

**Contexto:** además del módulo `Project` (Hito 1C, con código de aplicación real), existen migraciones de scaffolding inicial con tablas ya creadas pero **cero Models/Actions/Policies** en `app/Modules/` — mismo patrón que causó el conflicto de `cycles` en este hito (esquema adelantado, diseño potencialmente desactualizado frente a decisiones tomadas después).

| Migración | Tablas | Módulo(s) | Notas |
|---|---|---|---|
| `2027_01_01_000040_create_assessment_tables.php` | `rubrics`, `rubric_criteria`, `submissions`, `evaluations`, `evaluation_results`, `observations` | Assessment | FK diferida ya activa: `expected_evidences.rubric_id` → `rubrics.id`. `observations.project_id` es nullable. |
| `2027_01_01_000050_create_community_and_events_tables.php` | `forum_threads`, `forum_posts`, `chat_messages`, `learning_events` (SQL crudo, particionada, sin FKs) | Community + Tracking | `forum_threads.phase_id` es nullable. |
| `2027_01_01_000060_create_tracking_prediction_avatar_tables.php` | `student_progress`, `student_metrics`, `performance_snapshots`, `predictions`, `risk_alerts`, `avatar_messages`, `avatar_interactions`, `onboarding_steps` | Tracking + Prediction + Avatar | `student_progress.phase_id` y `risk_alerts.project_id` son nullable. |

`Analytics` y `Communication` (notificaciones) no tienen ningún scaffolding — ni siquiera tabla `notifications` — arrancan desde cero.

**Cuándo retomarlo:** al especificar el Hito 2 (Assessment) y sucesivos — verificar el diseño de cada tabla contra las decisiones tomadas hasta ese momento (mismo chequeo que reveló que `projects`/`phases`/etc. estaban desactualizadas frente a la decisión de `Cycle`) *antes* de escribir modelos/Actions encima, no después.

## 11. Criterio de desempate de `Evaluation::consolidatedLevel()` (Hito 2)

**Estado:** ✅ decisión de diseño tomada y probada — documentada aquí para que no quede solo en el docblock del método.

**Contexto:** el nivel consolidado de una evaluación (insumo que Tracking va a consumir para progreso/boletín, no expuesto en ningún reporte todavía) se calcula como la **moda** de los niveles de sus `EvaluationResult`. Cuando hay empate entre dos o más niveles con el mismo conteo (ej. 2 criterios en "Logro esperado" y 2 en "Logro destacado"), **gana el nivel más bajo** de los empatados — criterio conservador, no el más favorable al estudiante. Cubierto por `ConsolidatedLevelTest::test_a_tie_is_resolved_by_the_lowest_level`.

**Cuándo retomarlo:** si Tracking o el flujo de boletines necesita un criterio distinto (ej. promedio ponderado en vez de moda), es una decisión nueva a tomar en ese hito, no una corrección de esta — este criterio fue elegido específicamente para el cálculo cualitativo por evaluación, no para el consolidado numérico del boletín (fuera de alcance del Hito 2).

## 12. Integración futura: Google Workspace for Education (almacenamiento de archivos)

**Estado:** evaluado en profundidad, explícitamente diferido — no iniciar ningún trabajo de código ni exploración de la Google Admin Console hasta que se confirme la licencia permanente.

**Contexto:** la rectora está gestionando la licencia de Google Workspace for Education Fundamentals — hoy tiene una licencia temporal (vigente hasta septiembre, renovable) mientras se agenda la reunión de valoración para la licencia permanente. Fundamentals incluye 100 TB de almacenamiento compartido gratuito para todo el dominio — una solución real al límite de 2.44 GB de disco del hosting compartido actual, identificado en la verificación de infraestructura.

**Por qué se difiere, no se cancela:**
- La licencia todavía es temporal — construir integración real contra un estado no confirmado arriesga retrabajo si algo cambia en la transición a permanente.
- La Google Admin Console gestiona identidad y datos de todo el colegio, no es un entorno de desarrollo — cualquier configuración ahí (cuenta de servicio, delegación a nivel de dominio, unidades compartidas) debe hacerla la rectora misma como super administradora, siguiendo un instructivo preparado de antemano, nunca un agente explorando o piloteando directamente sobre el panel real. **Esto aplica en particular a Claude Code: no se debe usar la extensión de navegador ni ninguna forma de exploración semi-autónoma contra la Google Admin Console real bajo ninguna circunstancia, ni siquiera "solo para revisar opciones".**
- Involucra datos personales de menores — cualquier decisión de almacenamiento debe reflejarse primero en el texto legal definitivo de la Política de Tratamiento de Datos (todavía en borrador), mencionando a Google como encargado del tratamiento antes de que un solo archivo real de un estudiante viva ahí.

**Lo que ya está bien posicionado para cuando llegue el momento:** las columnas `file_disk`/`file_path`/`original_filename` de `submissions` (Hito 2) ya hacen el almacenamiento agnóstico al disco — conectar un driver de Google Drive más adelante es incremental, no requiere rediseñar el módulo de Evaluación.

**Diseño recomendado para cuando se confirme la licencia permanente** (documentado para referencia futura, nada de esto se construye todavía):
- Contenido institucional (guías, recursos oficiales que sube el colegio) → unidad compartida (Shared Drive) de Google, propiedad de la institución, vía cuenta de servicio con delegación a nivel de dominio autorizada por la rectora.
- Entregas de estudiantes → patrón más ligero orientado a consentimiento explícito por archivo (Google Picker API o equivalente), priorizando menor privilegio dado que son datos de menores.
- Video: considerar YouTube en modo no listado como complemento a Drive si el volumen de video crece, ya que Drive no está optimizado para streaming adaptativo.

**Cuándo retomarlo:** cuando la rectora confirme la licencia permanente de Google Workspace for Education. En ese momento, preparar un instructivo paso a paso para que ella misma configure la cuenta de servicio y la delegación desde la consola real — no delegarlo a exploración automatizada.

---

## Notas de infraestructura (resueltas)

- **`.env.testing`:** creado con `DB_DATABASE=liceo_innovarte_testing` explícito, para que `--env=testing` invocado manualmente no caiga por fallback a la base de desarrollo (`liceo_innovarte`), como ocurrió por accidente durante trabajo previo.
