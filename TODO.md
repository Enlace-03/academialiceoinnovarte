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

**Bug adicional encontrado (Hito de permisos, no corregido a propósito — es código muerto sin punto de entrada, corregirlo ahora no cambia ningún comportamiento real):** `AssignPermissionsAction::execute()` compara `$granter->hasRole('admin')` como atajo de "puede otorgar cualquier permiso del catálogo". Ese rol se llama `super_admin` en todo el resto del sistema (`HasDelegationCeiling::SUPER_ADMIN_ROLE`, `config('permissions.role_categories')`, `RolePermissionSeeder`) — un rol literal `admin` no existe, así que ese atajo nunca es cierto. Si algún día se retoma este subsistema, corregir esa línea a `hasRole('super_admin')` antes de conectarlo a nada; si no se corrige, un super_admin real quedaría limitado a delegar solo los permisos que su rol tiene explícitamente en `role_has_permissions` (hoy da la casualidad de que el seeder sincroniza el catálogo completo ahí, así que no se notaría hasta que un permiso nuevo se agregue al catálogo sin volver a correr el seeder).

**Cuándo retomarlo:** evaluar si se completan (corrigiendo el namespace, el bug de `hasRole('admin')` de arriba, y creando `UserGrant`) o se eliminan, cuando se diseñe el flujo real de creación de personal con permisos delegados por alcance.

## 5. Migración `user_grants` huérfana (sin modelo Eloquent ni consumidor activo)

**Estado:** tabla migrada (`2027_01_01_000070_create_user_grants_table.php`), sin modelo ni uso real.

**Contexto:** quedó huérfana tras simplificar `StudentPolicy::create()` (que era su único consumidor de lectura, vía un modelo `UserGrant` que nunca se creó). Pensada para permisos con alcance (ej. `students.create.scoped` limitado a ciertos grupos), complementando a Spatie.

**Cuándo retomarlo:** decidir si se elimina la tabla o se retoma como mecanismo de delegación con alcance más adelante — evaluando primero si el techo de delegación existente (`HasDelegationCeiling`, permisos completos sin scope por grupo) ya cubre el caso de uso real antes de resucitar un segundo sistema paralelo.

## 5b. Relation Manager espejo del lado acudiente ("Estudiantes a cargo")

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

**Mismo patrón que #18** (regla de integridad que solo vive en un formulario Filament, no en el modelo/BD) — este es el primer caso identificado, `GroupRequiresStudentRole` (Hito 3b-1) es el segundo.

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

## 10. Inventario de scaffolding de BD pre-creado para módulos futuros (Prediction/Avatar)

**Estado:** corregido — Assessment (Hito 2), Community (Hito 3a) y Tracking (Hito 4) ya tienen código de aplicación real (Models/Actions/Policies en `app/Modules/`, cada uno con su propia auditoría hecha en su momento) y se sacan de este inventario. Solo quedan **Prediction** y **Avatar** como scaffolding puro sin ningún Model/Action/Policy — referencia obligatoria antes de especificar esos dos hitos, no es deuda técnica, es un mapa para no repetir el conflicto de orden de migraciones del Hito 1C.

**Contexto:** además del módulo `Project` (Hito 1C) y de Assessment/Community/Tracking (ya con código real, ver arriba), queda una migración de scaffolding inicial con tablas ya creadas pero **cero Models/Actions/Policies** en `app/Modules/` — mismo patrón que causó el conflicto de `cycles` en el Hito 1C (esquema adelantado, diseño potencialmente desactualizado frente a decisiones tomadas después).

| Migración | Tablas | Módulo(s) | Notas |
|---|---|---|---|
| `2027_01_01_000060_create_tracking_prediction_avatar_tables.php` | `predictions`, `risk_alerts`, `avatar_messages`, `avatar_interactions`, `onboarding_steps` | Prediction + Avatar | `risk_alerts.project_id` es nullable. Las tablas de Tracking de esta misma migración (`student_progress`, `student_metrics`, `performance_snapshots`) ya tienen código real (Hito 4) y no forman parte de este inventario. |

`Analytics` y `Communication` (notificaciones) no tienen ningún scaffolding — ni siquiera tabla `notifications` — arrancan desde cero.

**Cuándo retomarlo:** al especificar el hito de Prediction o el de Avatar — verificar el diseño de cada tabla contra las decisiones tomadas hasta ese momento (mismo chequeo que reveló que `projects`/`phases`/etc. estaban desactualizadas frente a la decisión de `Cycle`) *antes* de escribir modelos/Actions encima, no después.

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

## 13. Acudiente actuando en nombre de un estudiante de ciclos 1-2 (transición-5°)

**Estado:** ✅ resuelto por diseño — la pregunta original quedó sin objeto, no sin resolver.

**Contexto (histórico):** el Hito 3b-0 solo daba cuenta propia (login) a estudiantes de ciclos 3-4 (6°-9°). Para ciclos 1-2, no se construyó ningún flujo alterno sin contraseña (PIN, selección de avatar) para que el estudiante pequeño entre directamente; eso quedó fuera de alcance a propósito. Las relaciones `User::children()`/`User::guardians()` ya existían y estaban probadas, y fueron la base real usada para resolver esto.

**Cómo se resolvió, sin necesidad de ningún campo híbrido "actuó en nombre de":**
- El dashboard del acudiente para ciclos 1-2 es estrictamente de solo lectura (progreso agregado, drill-down hasta el detalle de cada proyecto — ver #21) — la única acción interactiva es la gestión de la foto de perfil del estudiante (hito de moderación de foto), que no es participación académica y nunca se registra como tal.
- El acceso real "en el salón" para esos ciclos se resuelve vía entrega de sesión (Hito 3b-2, `GrantStudentSessionAction`): `Auth::loginUsingId()` hace que el sistema literalmente SEA el estudiante durante esa sesión, no el acudiente actuando en su nombre. Cualquier participación real (foro, chat, entrega) queda atribuida al estudiante real, sin ambigüedad ni campo especial.

**Pregunta original, ya sin objeto:** "¿la participación se atribuye al acudiente, al estudiante, o de forma híbrida?" — nunca hizo falta responderla, porque el acudiente nunca actúa en nombre del estudiante en ningún flujo que registre participación; en el único flujo donde hay participación real de un estudiante de ciclo 1-2 (entrega de sesión), el sistema ya es el estudiante.

## 14. Sin recuperación de cuenta autoservicio (Hito 3b-0)

**Estado:** decisión explícita — no se construyó "olvidé mi contraseña" ni ningún flujo de auto-registro.

**Contexto:** el login mínimo de `/` (`app/Livewire/Shared/Login.php`) no tiene ningún camino de recuperación de contraseña ni de registro — coherente con la regla absoluta de no self-signup del proyecto. La única vía de recuperación cuando un estudiante o acudiente olvida su contraseña es intervención manual de secretaría desde `/admin` (editar el usuario y fijar una contraseña nueva).

**Cuándo retomarlo:** si el volumen de solicitudes de recuperación manual se vuelve una carga operativa real para secretaría, evaluar un flujo de "olvidé mi contraseña" por correo — hoy no se justifica por el tamaño de la población (~200 estudiantes).

## 15. Acceso de docente al chat de grupo: aproximación pragmática sin `teacher_assignments` real (Hito 3)

**Estado:** decisión consciente, documentada como imprecisa a propósito — no es un bug, es la mejor opción disponible sin resucitar `teacher_assignments`.

**Contexto:** `chat_messages` se aísla por `group_id`, sin `project_id` ni ninguna relación de propiedad tipo `ProjectPolicy` (own/all). La tabla `teacher_assignments` (`teacher_id`, `subject_id`, `group_id`) existe migrada desde el Hito 1 pero sigue siendo scaffolding huérfano — sin Model, sin Actions, sin datos reales (ver punto 10) — así que no hay forma precisa hoy de saber "cuáles son los grupos de este docente". `ChatMessagePolicy::view()`/`create()` optaron por la aproximación más simple y explícita: **cualquier usuario de categoría staff puede ver/enviar mensajes en el chat de cualquier grupo**, sin acotar por proyecto ni ciclo propio. Es más abierto de lo ideal, pero acotado a personal del colegio (nunca a estudiantes ni acudientes), y la única acción realmente sensible — `hide()` (ocultar/moderar) — sí queda estrictamente restringida al permiso `chat.moderate` (solo rector y coordinator, nunca teacher).

**Contraste con un caso posterior que sí evitó esta imprecisión:** `PrivateChatThreadPolicy` (hito de chat privado) enfrentó la misma pregunta de fondo ("¿quién tiene autoridad real sobre este proyecto/grupo?") y, en vez de repetir el criterio abierto "cualquier staff, cualquier grupo", usó `ProjectPolicy::update()` (autoridad real, own/all) como base — posible porque el chat privado sí cuelga de `project_id`, a diferencia de `chat_messages`. No es que la lección de este punto se haya aplicado retroactivamente al chat grupal (sigue exactamente con el mismo criterio abierto de siempre) — es que un módulo nuevo, con la información necesaria disponible desde el diseño, no repitió la misma imprecisión.

**Cuándo retomarlo:** cuando `teacher_assignments` se implemente de verdad (Model + Actions + UI de asignación), reescribir `ChatMessagePolicy::view()`/`create()` para exigir que el docente tenga una asignación real sobre el grupo del mensaje, en vez de la puerta abierta a cualquier staff.

**Consumidor nuevo de este mismo riesgo (Hito 3b-2):** la acción "Entregar sesión" (`GroupsTable::grantSessionAction()`, panel `/academia`) reutiliza exactamente este criterio (`Gate::allows('create', [ChatMessage::class, $group])`) para decidir quién puede entregar la sesión de un grupo — a propósito, para no crear un permiso paralelo. Efecto directo: **cualquier docente puede entregar sesión en cualquier grupo, no solo el suyo**, mismo riesgo ya conocido de este punto, no uno nuevo. Cuando `teacher_assignments` se resuelva de verdad, la corrección beneficia a ambos consumidores a la vez.

## 16. `chat_messages` sin segmentación por proyecto (Hito 3)

**Estado:** riesgo conocido, aceptado — no bloquea el resto del módulo Community.

**Contexto:** a diferencia de `forum_threads`/`forum_posts` (que sí cuelgan de `project_id` y por tanto heredan el aislamiento por ciclo vía `ProjectPolicy`/`User::canAccessProject()`), `chat_messages` solo tiene `group_id` — es el chat general del grupo, no de un proyecto puntual. Esto es una decisión de diseño ya confirmada (un grupo puede tener chat activo aunque no haya un proyecto ABP corriendo en ese momento), no un descuido, pero significa que el chat no distingue "de qué proyecto están hablando" si el grupo tiene varios proyectos a lo largo del año.

**Cuándo retomarlo:** solo si en el futuro se necesita separar el chat por proyecto (ej. un grupo con dos proyectos simultáneos en distintas materias) — hoy (un proyecto por ciclo por semestre) no hay caso de uso real que lo requiera.

## 17. Subida de evidencia por el propio estudiante (Hito 3b-1)

**Estado:** ✅ resuelto (Hito 3b-3) — nuevo componente `App\Livewire\Student\EvidenceShow` (ruta `student.evidence.show`), pantalla dedicada por evidencia estilo Classroom (instrucciones+rúbrica / adjuntos+entrega / chat "Próximamente"). `RegisterSubmissionAction` extendida para múltiples adjuntos (foto+enlaces) con reconciliación (conserva/borra/crea), usada por igual desde el docente (Filament) y el estudiante (Livewire) — una sola Action, sin duplicar lógica.

**Contexto (histórico, antes de este hito):** `RegisterSubmissionAction` ya existía y ya soportaba archivo (`file_disk`/`file_path`/`original_filename`, un solo archivo) o texto (`text_content`). Ese esquema de columna escalar se reemplazó por la tabla hija `submission_attachments` (migración `2027_01_01_000400`, con migración de datos preservando las entregas ya registradas por docentes en el Hito 2) para admitir varios adjuntos por entrega.

**Política de autorización resuelta:** `SubmissionPolicy::create(User, ExpectedEvidence)` — estudiante del mismo ciclo (`canAccessProject()`); `SubmissionPolicy::update(User, Submission)` — solo el propio estudiante, y solo si `status === 'returned'`. Una entrega `submitted` (en espera de evaluación) o `evaluated` es de solo lectura para el estudiante; reabrir el estado `returned` sigue siendo un paso que hoy nada en el código realiza automáticamente (ver nota abajo).

**Pendiente real que queda abierto:** nada en el código actual pone una `Submission` en `status = 'returned'` — ni `EvaluateSubmissionAction` (siempre deja `evaluated`) ni ningún otro punto. `Submission::STATUSES` ya lista `'returned' => 'Devuelto'` y toda la UI de reentrega (botón "Volver a entregar" en `EvidenceShow`, rama `update()` de la Policy) ya está preparada para ese estado, pero hoy es inalcanzable en la práctica — falta la acción del lado del docente que decida "esta entrega no está lista, que la corrija" y la marque como `returned` en vez de evaluarla. Diseñar esa acción (probablemente en `ExpectedEvidencesRelationManager`, junto a `evaluateSubmissionsAction()`) es el siguiente paso natural, no parte de este hito.

## 18. Patrón: reglas de integridad que solo viven en un formulario Filament, no en el modelo/BD (Hito 3b-1)

**Estado:** patrón identificado y nombrado — un caso ya mitigado con una segunda capa independiente (`GroupChat`), otro (#7) todavía sin mitigar. Se documenta como patrón, no como hallazgo aislado, para reconocer más rápido una tercera instancia cuando aparezca.

**Contexto:** dos casos ya confirmados en el proyecto de la misma forma de riesgo — una combinación de datos que debería ser inválida (`group_id` no nulo sin rol `student`; un `parent_student` sin `data_treatment_consent`) solo se impide en el ÚNICO formulario Filament pensado para crearla, no en el modelo ni con un constraint de base de datos:

| Caso | Regla | Único punto donde se aplica | Nada la aplica en |
|---|---|---|---|
| `users.group_id` requiere rol `student` | `App\Rules\GroupRequiresStudentRole` | `UserForm` (Admin), campo `group_id` | `tinker`, seeders, cualquier Action futura, un cambio de rol posterior que no pase por ese formulario |
| Todo `parent_student` requiere `data_treatment_consent` (#7) | `RecordDataTreatmentConsentAction` | Attach action de `GuardiansRelationManager` | seeder, carga masiva Excel/CSV, `tinker` |

En ambos casos, la propia documentación de la regla ya lo advierte (`GroupRequiresStudentRole` dice explícitamente *"Integrity rule (application layer, not DB)"*). El riesgo no es que la regla esté mal escrita — es que solo hay **un** punto de entrada validado, y el dato en sí (`group_id`, `parent_student`) es alcanzable por cualquier otro camino de escritura sin pasar por él.

**Primera mitigación de este patrón, hecha en este hito:** `GroupChat::mount()` (Hito 3b-1) ya no confía en que `group_id` de una cuenta staff sea siempre `null` — agrega su propio `abort_unless(auth()->user()->hasRole('student'), 403)` como defensa en profundidad, independiente de si `GroupRequiresStudentRole` sigue siendo el único guardián de esa invariante y de si la ruta conserva el middleware `role:student`. Es la respuesta puntual para el consumidor de datos (`GroupChat`), no una solución de la causa raíz (la regla sigue viviendo solo en `UserForm`).

**Cuándo retomarlo:** si aparece una tercera instancia de este patrón, tratarla como una señal de que vale la pena una solución genérica (ej. mover estas reglas a un `Observer` del modelo, o agregar constraints reales de base de datos donde sea posible) en vez de seguir mitigando caso por caso en cada consumidor.

## 19. UI del portal de estudiante inapropiada para primaria/transición (Hito 3b-2)

**Estado:** stopgap consciente — se reutiliza tal cual la UI del Hito 3b-1, construida y pensada para ciclos 3-4 (6°-9°), como destino de una sesión entregada a un estudiante de ciclos 1-2 (transición-5°).

**Contexto:** el Hito 3b-2 (entrega de sesión docente→estudiante) redirige, tras `Auth::loginUsingId()`, a `/mis-proyectos` — las mismas pantallas de `MyProjects`/`ProjectShow`/`ForumThreadShow`/`GroupChat` que ya existían, con texto denso, sin avatar, sin iconografía grande, pensadas para un adolescente que lee bien, no para un niño de transición o primero. El propio skill `livewire-components` ya documenta la tabla "UX: reglas para primaria vs secundaria" (avatar visible y grande, máx. 3 opciones por pantalla, rúbrica solo color/ícono, sin porcentajes) — nada de eso está implementado todavía en ninguna pantalla del portal, ni de estudiante de ciclos 3-4 ni, ahora, de ciclos 1-2.

**Avance parcial (hito de estrellas):** el reemplazo de la barra de avance por `<x-progress-stars>` para ciclos 1-2 (`ProjectShow`, `PortalHome`, drill-down completo del acudiente) es la primera pieza concreta construida de lo que describe esta entrada — resuelve específicamente el punto "sin porcentajes" de la tabla de UX de arriba. El resto (avatar visible y grande, íconos grandes, máx. 3 opciones por pantalla, rúbrica solo color/ícono, lenguaje simplificado en general) sigue sin construir — esta entrada sigue abierta, no se marca resuelta.

**Cuándo retomarlo:** hito de diseño aparte, no incremental sobre 3b-1/3b-2 — requiere decidir el lenguaje visual completo (iconos, tipografía, narración de avatar) antes de tocar las pantallas existentes, en vez de ir parcheando cada componente por separado.

## 20. Destino futuro de la columna `avg_rubric_value` en `student_metrics` (Hito 4)

**Estado:** decisión de diseño tomada, no un vacío de especificación — misma familia de decisión que #11 (`Evaluation::consolidatedLevel()`).

**Contexto:** `RecalculateStudentProgressAction` (Tracking) nunca escribe `avg_rubric_value` — queda `null` a propósito (ver docblock de `StudentMetric.php`). La columna representaría un promedio numérico de niveles de rúbrica, exactamente el tipo de número que la regla absoluta #4 prohíbe exponer. El indicador cualitativo real que sí calcula este hito vive en `PerformanceSnapshot.metrics` como nivel dominante (moda de `EvaluationResult`, con el mismo criterio de desempate de #11: empate → nivel más bajo), nunca como promedio redondeado. Es la misma filosofía aplicada dos veces: #11 decide cómo consolidar niveles cualitativos sin recurrir a un número; este punto decide qué hacer con una columna que ya existe en el esquema y que tienta a hacerlo de todos modos.

**Recomendación:** no eliminar la columna ahora (ya migrada, `nullable`, no rompe nada dejarla en `null`), pero tampoco dejarla muerta para siempre sin una decisión — repropuesta como insumo numérico **interno** del futuro módulo Prediction, que sí necesita un valor real (no cualitativo) para calcular riesgo. Condición: si se usa, que sea estrictamente interno al cálculo de `risk_score`/`risk_level` (Prediction), nunca expuesto en ninguna UI o reporte como si fuera el indicador de calidad del estudiante — ese rol lo sigue cumpliendo el nivel dominante de `PerformanceSnapshot`.

**Cuándo retomarlo:** al especificar el módulo Prediction — decidir ahí, no antes, si Prediction calcula este promedio hacia esta misma columna o hacia un campo propio.

## 21. Dashboard del acudiente: solo lista de pendientes, sin avance ni nivel cualitativo (Hito 5a)

**Estado:** ✅ resuelto — funcionalmente completo, visualmente no diferenciado (ver matiz abajo).

**Contexto (histórico):** `PortalHome` (ruta `/`, compartida con el placeholder de estudiante) originalmente mostraba, para el rol `parent`, solo la lista de hijos vinculados (`User::children()`) y por cada uno sus evidencias pendientes con fecha límite próxima — a propósito **sin** barra de avance ni nivel cualitativo. Fue el requisito explícito de la especificación del paso 0 del Hito 5a, no una limitación técnica: solo se necesitaba "adónde llevar" al acudiente desde el correo de recordatorio de entrega (#3, Hito 5b), no replicar el portal de estudiante todavía.

**Lo que completó el dashboard, en hitos posteriores:**
- Progreso agregado por campo de pensamiento, por hijo (Hito 4b, `AggregateThinkingFieldProgressAction`).
- Selector de hijo con foto/ícono (hito de moderación de foto de estudiante).
- Drill-down completo — campos de pensamiento → proyectos → detalle de proyecto/evidencia — en modo estrictamente de solo lectura (hito de drill-down del acudiente).

**Matiz que sigue vigente:** `layouts/parent.blade.php` — un lenguaje visual propio y distinto para el acudiente, mencionado como aspiracional en el skill `livewire-components` — nunca se construyó. Todos los componentes del acudiente (`PortalHome`, `ChildThinkingFields`, `ChildFieldProjects`, `ChildProjectShow`, `ChildEvidenceShow`) siguen usando `layouts/portal.blade.php`, compartido con el estudiante. El dashboard es funcionalmente completo; no tiene una identidad visual propia.

**Si se retoma:** el lenguaje visual propio del acudiente queda como un hito de diseño aparte, no incremental — mismo criterio que #19 (requiere decidir el lenguaje visual completo antes de tocar componentes existentes).

## 22. Cron `schedule:run` en producción — no configurado todavía (Hito 5b)

**Estado:** nuevo requisito de infraestructura, pendiente de que Diego lo agregue en cPanel — no bloquea el desarrollo local.

**Contexto:** el job diario de recordatorios de entrega (`reminders:send-deadlines`) se registra en `routes/console.php` vía `Schedule::command(...)->dailyAt('07:00')`, el mecanismo estándar de Laravel. Para que corra en producción, el scheduler necesita su propio cron `* * * * * php artisan schedule:run` (Laravel decide internamente qué tareas programadas tocan ejecutarse cada minuto) — **distinto** del cron de `queue:work --stop-when-empty` ya confirmado en la verificación de infraestructura (ver `CLAUDE.md`, sección "Producción real", punto 1). Ese punto solo confirmó que `Cron Jobs` existe y funciona en cPanel, no que este segundo cron ya esté creado — hoy no existe ninguno.

**Cuándo retomarlo:** antes (o durante) el primer deploy real a producción — agregar el segundo cron en cPanel, ejecutado por la rectora o Diego, no por un agente. Paso a paso exacto (mismo patrón ya documentado para `queue:work`, sección "Producción real" de `CLAUDE.md`):

1. Entrar a cPanel de `academia.liceoinnovarte.edu.co`.
2. `Avanzada → Cron Jobs`.
3. En "Add New Cron Job", sección "Common Settings": elegir **"Once Per Minute (* * * * *)"** (o dejar los 5 campos de minuto/hora/día/mes/día-semana en `*`).
4. Campo "Command", el comando exacto:
   ```
   /usr/local/bin/ea-php83 /home/liceoinnovarteed/academia.liceoinnovarte.edu.co/artisan schedule:run >> /dev/null 2>&1
   ```
   Mismo binario PHP y misma ruta base que el cron de `queue:work` ya confirmado — solo cambia el subcomando de artisan. El `>> /dev/null 2>&1` es para no acumular correos de cron por cada ejecución silenciosa (Laravel decide internamente, cada minuto, si hay algo que tocaba correr o no).
5. Click "Add New Cron Job".
6. **Verificación, no dar por hecho que quedó bien:** esperar 1-2 minutos y correr `php artisan schedule:list` por SSH/terminal de cPanel si está disponible (confirma que `reminders:send-deadlines` aparece con su próxima ejecución), o revisar `storage/logs/laravel.log` tras la hora programada (`07:00`), o consultar la tabla `sent_deadline_reminders` en los días siguientes para confirmar que se están insertando filas.

**Advertencia para no repetir un hallazgo a medias:** este cron **no reemplaza** al de `queue:work`, hacen falta **los dos corriendo a la vez**. `schedule:run` solo decide "hoy toca ejecutar `reminders:send-deadlines`" y llama al comando directamente (síncrono) — pero `SendSubmissionDeadlineRemindersAction` dispara `$student->notify(...)`/`$guardian->notify(...)` con notificaciones que implementan `ShouldQueue`, así que quedan encoladas en la tabla `jobs` hasta que el cron de `queue:work` (el que ya existe) las procese y realmente las envíe. Sin ese segundo cron ya corriendo, los recordatorios se calculan y quedan en `sent_deadline_reminders` (marcados como "ya enviados") pero el correo/notificación real nunca sale — un bug silencioso, no un error visible.

## 23. Patrón: dos mecanismos de notificación no unificados, mismo motivo raíz que #18 (Hito 5a)

**Estado:** decisión de diseño confirmada, no una inconsistencia sin resolver — documentada como patrón para reconocerla más rápido si aparece una tercera variante.

**Contexto:** la campanita del portal estudiante/acudiente (`NotificationBell`) y el panel nativo de notificaciones de `/academia` (Filament) **no leen la misma clase de notificación**, aunque ambas escriben en la misma tabla física `notifications`:

| | `NotificationBell` (portal) | Panel nativo de Filament (`/academia`) |
|---|---|---|
| Query | `Illuminate\Notifications\DatabaseNotification::where(notifiable_type, notifiable_id)` — sin filtrar por formato | `vendor/filament/notifications/src/Livewire/DatabaseNotifications.php:112` — agrega `->where('data->format', 'filament')` |
| Quién escribe | `SubmissionDeadlineReminder` / `ForumReplyReceived` (extienden `Illuminate\Notifications\Notification`, `$user->notify(...)` normal) | `Filament\Notifications\Notification::make()->sendToDatabase()` (usado en `NotifyTeacherOfNewSubmission` / `NotifyTeacherOfForumActivity`) |

**Por qué son dos mecanismos y no uno:** se descubrió en vivo durante la verificación de este hito (no en el diseño original) — una notificación de Illuminate corriente queda bien guardada en `notifications`, pero el panel de Filament la filtra y nunca la muestra, porque no trae `data->format = 'filament'`. La corrección fue usar el constructor nativo de Filament para el lado de personal en vez de reproducir su formato a mano.

**Por qué hoy no es un problema real:** las dos poblaciones de destinatarios no se cruzan — estudiante/acudiente jamás entra a `/academia` (`User::canAccessPanel()` los excluye) y personal jamás ve la campanita del portal (esa ruta no las usa). `NotificationBell` técnicamente no filtra por `format`, así que si algún día un mismo usuario recibiera de ambos tipos, sí las mostraría todas mezcladas — pero ese escenario no existe hoy.

**Cuándo retomarlo:** si aparece una tercera variante de este patrón (un tercer lugar que muestre notificaciones con su propio criterio de formato/filtro), tratarlo como el mismo caso que #18 — señal de que conviene una capa única de "centro de notificaciones" en vez de que cada consumidor de la tabla `notifications` decida su propio filtro por separado.

## 24. Notificaciones al docente sin agrupar: una por post/entrega, sin límite (Hito 5a)

**Estado:** riesgo conocido, aceptado a propósito para esta primera versión — no implementado ningún mecanismo de agrupación todavía.

**Contexto:** `NotifyTeacherOfForumActivity` corre en cada `ForumPostCreated` y `NotifyTeacherOfNewSubmission` en cada `SubmissionRegistered`, sin agrupar ni acumular — una notificación de plataforma por evento, sin tope. Como `RegisterSubmissionAction` dispara el evento también en re-entregas (`updateOrCreate`, decisión ya confirmada del Hito 2), una evidencia devuelta y corregida genera una notificación nueva cada vez, no solo la primera.

**Por qué se deja así por ahora:** con el volumen actual (~200 estudiantes, un docente con 1-3 proyectos activos a la vez, tráfico de foro moderado) el riesgo es bajo — no se bloqueó el hito por esto. Pero no es hipotético: un hilo con 20-30 respuestas en un día ya generaría 20-30 notificaciones individuales para ese docente, ahogando las que sí importan (una entrega real). El patrón más natural cuando eso ocurra no es silenciar, sino agrupar (ej. un resumen diario "hay actividad nueva en 2 de tus proyectos" en vez de notificación por evento).

**Cuándo retomarlo:** si el volumen real de un docente activo empieza a generar más de, digamos, 10-15 notificaciones/día de forma sostenida — señal de que vale la pena introducir un job de resumen diario en vez de notificación por evento, sin necesidad de rediseñar los listeners actuales (seguirían escribiendo el detalle crudo en algún lado; cambiaría solo cómo se agrupa antes de notificar).

## 25. Patrón: assets de Tailwind sin recompilar en local generan bugs visuales silenciosos (Hito 5a, segunda vuelta)

**Estado:** causa raíz identificada y corregida para el caso puntual (badge de `NotificationBell`) — documentado como patrón porque va a repetirse con cualquier clase de Tailwind nueva mientras el flujo de trabajo local no cambie.

**Contexto:** este WampServer local sirve `public/build` **pre-compilado** — no hay ningún proceso de Vite con HMR corriendo (confirmado: `ps aux | grep vite` vacío). El skill `git-workflow` ya documentaba correctamente que `npm run build` corre en local antes de comitear a la rama `deploy`, pero eso deja implícito (y aquí se demostró falso en la práctica) que development local no lo necesita. **Sí lo necesita**: cualquier clase de Tailwind que aparezca por primera vez en un `.blade.php` nuevo no está en el CSS ya compilado hasta que alguien corra `npm run build` de nuevo — sin eso, el elemento se renderiza sin esa clase, sin ningún error ni advertencia visible.

**Bug real que produjo esto:** el badge de no-leídas de `NotificationBell::class` (`bg-red-500`, `-top-1`, `-right-1`, `h-4`, `w-4`, `rounded-full`) existía correctamente en el DOM con el número correcto desde el primer round del Hito 5a — pero era completamente invisible en el navegador, porque nadie corrió `npm run build` después de crear ese archivo. Pasó inadvertido en la primera verificación en Chrome de ese hito porque esa verificación se enfocó en el *contenido de texto* de las notificaciones, no en el estilo visual del badge — se detectó recién en la segunda vuelta, al inspeccionar explícitamente `getComputedStyle()` del elemento vía `javascript_tool`.

**Cómo se detectó (para reconocerlo más rápido la próxima vez):** un elemento que aparece en el DOM (confirmado con `document.querySelector(...).outerHTML`) pero cuyo `getComputedStyle()` no refleja las clases de Tailwind esperadas (colores/posiciones en sus valores por defecto en vez de los de la clase) es la señal — no un error de consola, no un fallo de test, solo una diferencia silenciosa entre lo que el HTML dice y lo que el CSS compilado sabe.

**Cuándo retomarlo:** correr `npm run build` como parte del checklist de cierre de cualquier hito que agregue o modifique un `.blade.php` con clases de Tailwind nuevas — no solo antes del deploy. Si esto sigue mordiendo en hitos futuros, vale la pena evaluar activar el dev server de Vite (`npm run dev`) para este entorno en vez de depender de recordar recompilar manualmente.

**Relacionado, causa raíz distinta:** el hallazgo posterior del tema Vite propio de `/academia` (`resources/css/filament/academic/theme.css`, ver `CLAUDE.md` sección "Producción real", punto 3) es el mismo síntoma general — CSS compilado sin las clases del proyecto, bug visual silencioso — pero causa raíz distinta: ahí faltaba registrar el tema propio del panel académico (`AcademicPanelProvider::viteTheme()`), no un `npm run build` pendiente de correr.

---

## 26. Video en galería/foro: solo YouTube no listado incrustado, nunca subida directa (Hito de galería)

**Estado:** explícitamente fuera de alcance de este hito — no implementado.

**Contexto:** el hito de galería (GalleryPost/GalleryPhoto, fotos adjuntas en ForumPost) cubrió solo imagen. Video quedó deliberadamente afuera por decisión explícita de Diego: cuando se retome, el mecanismo debe ser un enlace a un video de YouTube **no listado** (`unlisted`, no público en el canal) incrustado vía `<iframe>` en la galería, y opcionalmente como enlace simple en el foro — **nunca** subida directa de archivo de video. Nada de MediaLibrary ni de un mecanismo de archivo propio para video: no hay presupuesto de almacenamiento para eso (ver punto 15 de "Producción real" en `CLAUDE.md` — 2.44 GB de cuota total) ni razón para reinventar transcodificación/streaming que YouTube ya resuelve.

**Cuándo retomarlo:** cuando se diseñe ese hito. Falta definir: quién sube el video a la cuenta de YouTube del colegio (fuera de esta plataforma), qué campo captura la URL/ID del video, y si aplica la misma autorización de audiencia (`GalleryPostPolicy`) al INCRUSTADO o si "no listado" ya es suficiente barrera dado que YouTube no lo indexa ni lo muestra en el canal público — probablemente no es suficiente por sí solo si se quiere el mismo aislamiento entre ciclos que ya exige el resto de la app, vale la pena decidirlo explícitamente antes de implementar.

## 27. Texto legal de tratamiento de datos: falta mencionar explícitamente el uso de imagen/fotos de estudiantes

**Estado:** pendiente — no es un bloqueante técnico, es un pendiente de contenido legal.

**Contexto:** con GalleryPost/GalleryPhoto, las fotos adjuntas en ForumPost, y la foto de perfil del estudiante (`users.photo_path`, subida por el acudiente en ciclos 1-2, hito de moderación de foto), la plataforma ahora almacena y muestra fotografías reales de estudiantes (menores de edad) dentro de `/` y `/academia`. El texto legal definitivo de tratamiento de datos (mismo pendiente ya anotado para la integración de Google Workspace, ver TODO.md #12) debe mencionar explícitamente el uso de imagen/fotografía de los estudiantes en la plataforma — las tres fuentes, no solo galería/foro —, no solo el tratamiento de datos académicos — son categorías de dato distintas y el consentimiento de uno no cubre automáticamente al otro.

**Cuándo retomarlo:** junto con el texto legal definitivo de tratamiento de datos (mismo hito que #12), antes de que la galería tenga uso real con fotos de estudiantes en producción — no antes de eso, pero tampoco después.

## 28. Patrón: un Action compartido entre Filament y Livewire recibe archivos en formas distintas (Hito 3b-3)

**Estado:** identificado y resuelto para `RegisterSubmissionAction` — documentado como patrón para reconocerlo más rápido si una futura Action también sirve a ambos lados (Filament admin/académico y Livewire estudiante/acudiente).

**Contexto:** `RegisterSubmissionAction::reconcileAttachments()` crea adjuntos tipo foto a partir de dos formas de entrada completamente distintas, según quién llama:
- **Livewire** (`EvidenceShow`, estudiante): `WithFileUploads` entrega un `TemporaryUploadedFile` sin guardar — el Action es quien lo guarda (`->store('submissions', 'local')`).
- **Filament** (`ExpectedEvidencesRelationManager`, docente): el campo `FileUpload` ya guardó el archivo en disco **antes** de que corra el `action()` closure — por defecto, Filament no entrega un objeto de archivo temporal, entrega la ruta ya persistida (string). Pasar ese string a un método que espera `->store()`/`->getClientOriginalName()` es un `Call to a member function ... on string` en producción, no un error visible en desarrollo si no se prueban ambos caminos.

**Cómo se resolvió:** `reconcileAttachments()` acepta dos formas del ítem `type=photo` (`file` para el crudo de Livewire, `stored_path`+`original_filename` para el ya guardado de Filament) en vez de que cada caller duplique la lógica de creación/compresión. Ambas formas terminan en el mismo `SubmissionAttachment::create([...])`, así que la compresión (`SubmissionAttachment::booted()`, evento `created()`) corre igual sin importar el origen — no hay riesgo de que un adjunto del docente quede sin comprimir.

**Cuándo retomarlo:** al construir la próxima Action de dominio invocada tanto desde un formulario Filament con `FileUpload` como desde un componente Livewire con `WithFileUploads` — verificar desde el diseño si el Action necesita esta misma dualidad de forma de entrada, en vez de descubrirlo recién al conectar el segundo caller.

## 29. `<x-rubric-criteria-table>`: sin auto-expandir el criterio con peor nivel (Hito 3b-3, segunda vuelta)

**Estado:** decisión consciente de no construirlo todavía — comparación directa contra Google Classroom (que tampoco lo hace; sus criterios también parten colapsados por igual).

**Contexto:** al agregar el resaltado por criterio (`resultsByCriterion`, ver `EvidenceShow::resultsByCriterion()` y el `Placeholder` homónimo de `evaluateSubmissionsAction()`), cada criterio de la rúbrica arranca colapsado (`<details>` nativo, sin `open`) con el nivel logrado visible en el resumen de la fila. Una mejora posible: abrir automáticamente el criterio (o los criterios) donde el estudiante sacó el nivel más bajo, para que salte a la vista sin que nadie tenga que expandir manualmente fila por fila.

**Por qué no ahora:** el pedido explícito de este hito fue "sin lógica de abrir automáticamente el peor criterio por ahora" — con 2 criterios (los datos de prueba actuales) no hace falta; con una rúbrica de 5-6 criterios probablemente sí se sienta la falta.

**Cuándo retomarlo:** si en el uso real aparecen rúbricas con varios criterios y retroalimentación de docentes/estudiantes de que cuesta encontrar el criterio más débil sin expandir todo. Requiere decidir el criterio de "peor" cuando hay empate en el nivel más bajo (mismo tipo de decisión que ya tomó `Evaluation::consolidatedLevel()`, ver punto #11 — no asumir que se resuelve igual sin pensarlo).

## 30. Hito de Boletines (parcial/final/retiro) — pendiente, incluye nivel cualitativo agregado por campo de pensamiento

**Estado:** no iniciado — mencionado explícitamente al cerrar el hito de progreso agregado por campo de pensamiento (Hito 4b) para que quede anotado antes de perderse.

**Contexto:** el Hito 4b (`AggregateThinkingFieldProgressAction`) implementó **solo la barra mecánica agregada** por campo de pensamiento (promedio simple de `progress_pct` entre los proyectos activos que tocan cada campo) — deliberadamente **sin** nivel cualitativo agregado por campo, y sin ninguna pantalla interactiva para él. Ese nivel cualitativo agregado es justamente lo que le falta al hito de Boletines: tres variantes (parcial, final, retiro), cada uno debe calcular y mostrar el **nivel cualitativo agregado por campo de pensamiento** — no solo el avance mecánico.

**Cálculo del nivel cualitativo agregado (cuando se construya):** mismo criterio de desempate que `Evaluation::consolidatedLevel()` y `RecalculateStudentProgressAction::dominantQualitativeLevel()` (ver punto #11) — **moda** de los niveles cualitativos entre los proyectos/criterios que tocan el campo, empate resuelto por el **nivel más bajo** de los empatados (criterio conservador, no el más favorable). Convertido a la **escala numérica de 5 niveles ya confirmada por Rafa** — la conversión exacta (mapeo de los 4 niveles cualitativos vigentes, `inicio/en_proceso/logro_esperado/logro_destacado`, a esa escala numérica de 5) todavía no está documentada en este repositorio y hay que precisarla al especificar este hito, no asumirla.

**Dónde se muestra (ya decidido, no reabrir cuando se construya):** únicamente dentro del **documento del boletín generado** (PDF o el formato que se decida) — **nunca** como pantalla interactiva del portal, ni del estudiante ni del acudiente ni de `/academia`. Coherente con la regla absoluta #4 del proyecto (los niveles de rúbrica nunca se muestran como número) aplicada con más cuidado todavía acá, porque acá SÍ se convierte a número — pero solo dentro del documento oficial, no en ninguna UI de uso diario.

**Cuándo retomarlo:** al especificar el hito de Boletines — requiere además decidir el disparador de cada variante (parcial/final/retiro), el formato de salida, y quién lo genera (¿el propio docente desde `/academia`? ¿un job programado?) — nada de eso está definido todavía, solo el cálculo cualitativo agregado que este punto documenta para no perderlo.

## 31. Sin fixture centralizado de cuentas de prueba (`@test.local`)

**Estado:** patrón recurrente confirmado — ya no es un riesgo hipotético, no resuelto todavía.

**Contexto:** identificado originalmente al investigar por qué `rectora.prueba@test.local` tenía una contraseña distinta al resto de las cuentas de prueba (hito de foto de perfil de estudiante). En esa sesión se habían creado siete cuentas de prueba (`docente.prueba`, `estudiante.tercero`, `estudiante.prueba`, `acudiente.prueba`, `rectora.prueba`, y dos más puntuales) con el dominio `@test.local`, todas ad-hoc vía `php artisan tinker` en momentos distintos — **ninguna vive en un seeder**. Se confirmó grepeando `app/` y `database/`: cero referencias a `test.local` en el código. La contraseña `"password"` fue una convención repetida la mayoría de las veces, nunca garantizada — así fue como `rectora.prueba` terminó con una distinta sin que nada lo detectara, hasta que un login real falló.

**Confirmado como patrón, no un caso aislado:** desde que se documentó esta entrada, el mismo camino manual por `tinker` se repitió en al menos tres hitos más — dashboard del acudiente (drill-down), chat privado, y estrellas —, cada uno creando cuentas `@test.local` ad-hoc nuevas para poder verificar en Chrome. Ya no es un riesgo hipotético ligado a "si el equipo crece" — es un patrón recurrente confirmado en la práctica, cada vez que hace falta una cuenta de prueba nueva.

**Por qué importa:** no es solo la molestia puntual de una contraseña — sin un fixture centralizado, reconstruir el entorno de pruebas de dev (o dárselo a alguien más del equipo) depende de memoria de sesiones de chat pasadas, no de algo reproducible con un comando. Cada cuenta nueva creada ad-hoc es una oportunidad más de inconsistencia silenciosa como esta.

**Cuándo retomarlo:** el disparador original ("si el equipo crece más allá de Diego, o si reconstruir el entorno de dev se vuelve una tarea frecuente") ya se cumplió por el patrón recurrente de arriba, no por crecimiento de equipo. La recomendación técnica sigue siendo la misma: un `DevFixturesSeeder` (o similar, fuera de `DatabaseSeeder` para no correr en producción) que cree estas cuentas con `firstOrCreate` y contraseña fija — ahora con más urgencia real detrás, no una mejora especulativa.

## 32. Chat privado — visibilidad institucional deliberadamente separada de la autoridad de escritura (gobernanza, ya implementada)

**Estado:** ✅ decisión de gobernanza confirmada y verificada, no deuda técnica — se documenta acá para que no se reabra ni se funda por accidente en un ajuste futuro de `config/permissions.php`.

**Contexto:** `private_chats.view.all` (coordinator/rector) es deliberadamente de **solo lectura**. La Ley 1620 de 2013 exige que la institución pueda vigilar el trato de personal hacia estudiantes (protección escolar contra la violencia) — de ahí la lectura universal. Pero el principio de minimización de datos de la Ley 1581 de 2012 (mismo criterio ya aplicado en `RecordDataTreatmentConsentAction`/`DataTreatmentConsent`) exige que esa vigilancia no se convierta en autoridad operativa de facto: escribir en un chat puntual sigue exigiendo autoridad **real** sobre ese proyecto (`projects.update.all`, o ser el docente responsable vía `projects.update.own`), nunca el solo hecho de poder leerlo. `PrivateChatThreadPolicy` ya separa `view()`/`viewContext()` (lectura, incluye `.view.all`) de `create()` (escritura, nunca incluye `.view.all`) — verificado por `PrivateChatThreadPolicyTest::test_institutional_viewer_with_only_view_all_can_read_but_not_write` y `test_coordinator_with_real_project_authority_can_also_write`. El comentario completo vive junto a `private_chats.view.all` en `config/permissions.php` (catálogo y presets).

**Cuándo retomarlo:** nunca, salvo que cambie la base legal — si algún ajuste futuro a los presets de `coordinator`/`rector` termina otorgando escritura por el solo hecho de tener `private_chats.view.all`, es una regresión de esta decisión, no una mejora.

## 33. Acceso a chat desde la tarjeta de proyecto (Hito de dashboard enriquecido)

**Estado:** no implementado, diferido explícitamente por costo bajo de agregar después.

**Contexto:** el rediseño de `MyProjects` (grid de tarjetas de proyecto, "Próxima entrega", calendario) no agrega un acceso directo al chat de equipo (`ProjectShow::myTeam()`/chat privado) desde la propia tarjeta -- hoy sigue requiriendo entrar al proyecto primero. No es una limitación de datos: `ProjectTeam`/`PrivateChatThread` ya existen y `ProjectShow` ya resuelve `myTeam()` para el proyecto individual: agregar el enlace es solo trabajo de UI en la tarjeta, sin cambios de dominio.

**Cuándo retomarlo:** cuando el flujo real de uso muestre que entrar al proyecto solo para llegar al chat es fricción frecuente -- de momento no se sabe si el estudiantado lo pedirá.

## 34. Calendario del estudiante: sin filtro por proyecto ni eventos institucionales (Hito de dashboard enriquecido)

**Estado:** fuera de alcance explícito de la primera versión del calendario (`App\Livewire\Student\MyCalendar`).

**Contexto:** el calendario nuevo solo marca fechas límite de `StudentPhaseSchedule` (evidencias esperadas todavía sin resolver, vía `ResolvePendingEvidencesForStudentAction`) -- no distingue por proyecto (un estudiante con varios proyectos activos ve todas las entregas mezcladas en el mismo mes) y no muestra ningún evento puramente institucional (ej. jornada pedagógica, entrega de boletines, día festivo) porque no existe hoy ninguna entidad de "evento" en el dominio -- solo hay fechas derivadas de fases/evidencias.

**Cuándo retomarlo:** filtro por proyecto es una mejora de UI menor cuando el volumen de proyectos simultáneos por estudiante lo justifique. Eventos institucionales requiere diseñar una entidad nueva primero (¿tabla `institutional_events`? ¿alcance por ciclo/grado o global? ¿quién la administra -- rector, cualquier docente?) -- no es una extensión trivial de lo que ya existe, es una pieza de dominio nueva.

---

## Notas de infraestructura (resueltas)

- **`.env.testing`:** creado con `DB_DATABASE=liceo_innovarte_testing` explícito, para que `--env=testing` invocado manualmente no caiga por fallback a la base de desarrollo (`liceo_innovarte`), como ocurrió por accidente durante trabajo previo.
