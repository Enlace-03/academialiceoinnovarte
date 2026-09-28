# SECURITY.md — Academia Liceo Innovarte

Registro de hallazgos de seguridad corregidos, decisiones de diseño confirmadas
(que **no** son bugs) y pendientes antes del primer deploy con datos reales.

**Fuente de la sección 1:** los 22 commits de `f8990ba` a `f93f29e` (ambos
inclusive), leídos del mensaje y del diff de cada uno. Dos commits del rango no
son correcciones de seguridad en sentido estricto y se listan aparte al final de
la sección 1: `42206e0` (validación de formulario) y `5d937d9` (funcionalidad de
moderación).

---

## 1. Hallazgos corregidos

### Permisos y techo de delegación

| Commit | Problema | Cambio |
|---|---|---|
| `f8990ba` | `UserPolicy::view()` solo pedía `users.view`: se podía ver a cualquier usuario, incluidos `super_admin` o personal con más permisos que uno mismo. | `view()` aplica ahora el mismo `canManageUser()` que ya usaban `update()` y `delete()`. |

### Login y sesión

| Commit | Problema | Cambio |
|---|---|---|
| `0991190` | El login del portal no limitaba intentos fallidos. | 5 fallos por 60 s por pareja correo+IP (no solo IP: muchos estudiantes comparten la IP pública del colegio). Solo cuentan los fallos, un login exitoso limpia el contador, y el bloqueo aplica aun con la contraseña correcta. Los paneles Filament ya tenían su propio límite. |

### Sesión de estudiante entregada

| Commit | Problema | Cambio |
|---|---|---|
| `ec08da6` | Un `wire:poll` sin actividad real del usuario renovaba `active_grant_last_seen_at` en cada tick, así que una sesión entregada con la pestaña abierta y sin interacción nunca expiraba. | Los sondeos puros ya no renuevan la sesión. Se verificó contra el JS real de Livewire que un `wire:poll` sin expresión viaja como `$wire.$commit()` (sin `calls` ni `updates`) y no como `calls:[{method:'$refresh'}]`; se detectan ambas formas por si acaso. |

### `users.is_active`

| Commit | Problema | Cambio |
|---|---|---|
| `ca373c6` | La columna existía pero nada la aplicaba: un usuario desactivado seguía entrando al portal y a los paneles. | Login del portal: con contraseña correcta muestra "Tu cuenta esta desactivada" y no inicia sesión. `canAccessPanel()` de ambos paneles exige `is_active` (Filament lo re-evalúa en cada petición → 403). Middleware `EnsureUserIsActive` (grupo `web`) corta sesiones ya abiertas en el portal y en `POST /livewire/update`, cerrando la entrega de sesión si la había; cubre también la cookie "recordarme" de acudientes. No se puede entregar sesión a un estudiante inactivo. Cast `boolean` y default `true` en el modelo. El toggle `is_active` queda deshabilitado sobre el propio registro. |

### Cabeceras de seguridad

| Commit | Problema | Cambio |
|---|---|---|
| `c74f7b6` | No había cabeceras de seguridad globales. | Middleware `SecurityHeaders` registrado como global (no solo en el grupo `web`, porque los paneles Filament arman su propio stack): `X-Frame-Options`, `X-Content-Type-Options` y `Referrer-Policy` en toda respuesta; HSTS solo en producción sobre HTTPS. Sin CSP global por ahora. |

### Dependencias

| Commit | Problema | Cambio |
|---|---|---|
| `75212b4` | 26 avisos de `composer audit`. | `composer update` de filament, livewire, commonmark, guzzle y dompdf con `--with-all-dependencies`: filament 4.11.8 → 4.14.0, livewire 3.8.2 → 3.8.9, commonmark 2.8.2 → 2.10.3, guzzle 7.14.0 → 8.2.0 (versión mayor; sin uso directo en `app/`), dompdf 3.1.5 → 3.1.6. Arrastra laravel/framework 13.19 → 13.33 y symfony 7.4.x. Incluye los assets de Filament republicados por `filament:upgrade`. |

### Tipos de archivo en subidas y servido de archivos privados

| Commit | Problema | Cambio |
|---|---|---|
| `6511e3a` | `->image()` de Filament acepta `image/*`, que incluye `image/svg+xml` (puede llevar scripts). | La galería y el registro de entrega por el docente aceptan solo jpeg/png/webp/gif, igual que la regla `image` de Laravel del lado Livewire. Tests con un SVG real y control positivo con PNG. |
| `38b3f14` | Un archivo privado abierto directo en el navegador podía ejecutar scripts si era un SVG o HTML disfrazado. | Las cuatro rutas de archivos del disco privado (fotos de galería y foro, adjuntos de entrega, foto de perfil) responden con `Content-Security-Policy: default-src 'none'; sandbox`. Defensa en profundidad sobre la validación de tipo. Las rutas de adjuntos de evaluación (`0f6f66a`) usan el mismo middleware. |
| `10f21df` | Las entregas no admitían documentos; había que admitirlos sin abrir la puerta a archivos disfrazados. | Nueva regla `App\Rules\ValidDocumentUpload` (estudiante y docente): además de `mimes:pdf,docx,xlsx,pptx` (detección por contenido vía fileinfo), valida que un PDF empiece por `%PDF-` y que un docx/xlsx/pptx sea un ZIP válido con la estructura interna esperada y **sin `vbaProject.bin`** (macros). El tipo se decide con `guessExtension()` (por contenido), nunca por el nombre que manda el cliente. Los documentos se sirven con `Content-Disposition: attachment`; las fotos siguen inline. |
| `68bfacb` | Un `original_filename` con `/` o `\` (p. ej. `../../.env`) hacía que Symfony lanzara `InvalidArgumentException` y la descarga respondiera 500. | La ruta aplica `basename()`, quita caracteres de control y separadores, y cae en `documento` si queda vacío. Test con nombre de path traversal. |

### Enlaces (esquemas y `target="_blank"`)

| Commit | Problema | Cambio |
|---|---|---|
| `1134794` | La regla `url` de Laravel sin restricción de esquema deja pasar `javascript:` y otros; el enlace se guarda tal cual y se renderiza en `<a href>` (`x-youtube-embed`), ejecutándose al hacer clic. | `url:http,https` en `EvidenceShow` (`linkInput`, `newLinks.*.url`) y en el mismo campo de `ExpectedEvidencesRelationManager` (panel del docente). |
| `b352088` | `TextInput::url()` de Filament solo agrega la regla `url`, sin esquema; además `url_or_path` de Resource nunca tuvo validación, así que puede haber registros viejos con esquema no-web. | Regla explícita `url:http,https`, más `Resource::hasHttpUrl()` como defensa en profundidad en las vistas de estudiante y padre (un esquema no-web cae a texto plano, no a un `<a href>` clicable). |
| `e6c1b7d` | `<a target="_blank">` sin `rel`. | `rel="noopener noreferrer"` en todos los del proyecto (grep completo): youtube-embed, adjuntos de entrega en estudiante, padre y panel docente, fotos de foro y galería, y los enlaces estáticos del scaffold en `welcome.blade.php`. |

### Foro y chat

| Commit | Problema | Cambio |
|---|---|---|
| `8be5a4a` | `parent_post_id` no validaba que el post referenciado perteneciera al mismo hilo; la FK sola no lo impedía. Confirmado en vivo: el post huérfano aparecía anidado bajo su padre real al ver el otro hilo (`ForumPost::replies()` no filtra por `forum_thread_id`), exponiendo contenido a un hilo para el que el autor no estaba autorizado. | Se rechazan respuestas dirigidas a un post de otro hilo. |
| `a0ffb92` | Sin límite de frecuencia en chat ni foro. | `GroupChat::send` y `PrivateChatPanel::send`: máximo 15 por minuto por usuario. `ForumThreadShow::createPost` y `submitReply` comparten un solo cupo de 10 por minuto (ambas terminan en `CreateForumPostAction`; por separado permitirían 20/min alternando). Al excederlo, error de validación amigable en el campo. |
| `f019966` | `chat_messages.user_id`, `private_chat_messages.user_id`, `forum_posts.user_id` y `forum_threads.created_by` usaban `cascadeOnDelete()`: borrar un usuario borraba historial institucional que otros ya leyeron. | Pasan a `restrictOnDelete()`. `EditUser` captura el caso ANTES de la BD y pide desactivar al usuario en vez de borrarlo. Verificado antes de migrar: cero dependencias del cascade en la suite. |
| `5f677ec` | Complemento de `f019966`: la FK restrictiva podía dispararse igual (carrera entre el chequeo previo y el DELETE) y dejar una excepción sin manejar. | Mensaje exacto "No se puede eliminar: este usuario tiene mensajes o publicaciones. Desactívalo en su lugar."; `using()` captura `QueryException` SQLSTATE 23000 y devuelve `false` para que salga la notificación de fallo. |

### Adjuntos de evaluación (documento de retroalimentación del docente)

| Commit | Problema / motivo | Cambio |
|---|---|---|
| `2e3593e` | El docente solo podía devolver texto; el archivo debía quedar como devolución del docente y nunca mezclado con la evidencia del estudiante. | Tabla `evaluation_attachments` y modelo `EvaluationAttachment`, ligados a la `Evaluation` (no a la `Submission`); un adjunto por evaluación (`unique(evaluation_id)`); el hook `deleting` borra el archivo. Un archivo nuevo reemplaza al anterior (el viejo se borra del disco tras confirmar la transacción); sin archivo, se conserva. `stored_path` se restringe a `evaluation-feedback/` para que el cliente no pueda apuntar el adjunto a un archivo privado ajeno. |
| `0f6f66a` | Autorización del archivo de retroalimentación. | Ruta `evaluations.attachments.show` (UUID, disco `local`, descarga con `Content-Disposition: attachment`, CSP sandbox) autorizada por `EvaluationAttachmentPolicy::view()`: estudiante dueño, sus acudientes, el docente que evaluó y quien tenga `observations.view.all`. Deliberadamente más angosta que `SubmissionPolicy::view()`: un docente distinto con acceso al proyecto vía `viewAsStaff` no puede verlo. Nombre de descarga saneado con `SafeDownloadName`, compartido con `submissions.attachments.show`. |
| `f93f29e` | Mostrar el documento en las vistas de proyecto. | `project-show` y `child-project-show` muestran el documento junto al comentario de texto, fuera del `<a>` de la tarjeta (un enlace dentro de otro es HTML inválido). Sin cambios de autorización. |

### Otros commits del rango (no son correcciones de seguridad)

- `42206e0`: el título de hilo de foro no tenía `maxLength`; solo la columna de BD lo frenaba, con un error genérico. Ahora el formulario del docente limita a 255 caracteres con validación clara.
- `5d937d9`: nueva página `GroupChatModeration` en `/academia` (permiso `chat.moderate`, mismo patrón que `PrivateChats`); oculta mensajes vía `HideCommunityContentAction`, autorizado por `ChatMessagePolicy::hide()`. Es funcionalidad de moderación, no una corrección.

---

## 2. Decisiones de diseño confirmadas (no son bugs)

Estas decisiones fueron confirmadas explícitamente. **No las "corrijas"** creyendo
que son descuidos. Ninguna tiene un commit propio en el rango de la sección 1,
salvo donde se indica.

1. **`UserPolicy::update()` vía `students.create` edita TODOS los campos** del
   estudiante/acudiente, no solo el vínculo de acudiente. Alcance intencional.
   El código (`app/Policies/UserPolicy.php`, `update()`) restringe el objetivo por
   rol (`student`/`parent`), no por campo. Sin commit en el rango (`f8990ba`
   cambió `view()`, no `update()`).
2. **`ExpireDeliveredStudentSession` usa solo ventana deslizante de 50 min de
   inactividad**, sin tope absoluto adicional (`MAX_IDLE_MINUTES = 50`).
   Suficiente por ahora; el botón "Terminar clase" cubre el resto. Relacionado,
   pero no es esta decisión: `ec08da6` (los sondeos puros ya no renuevan la
   ventana).
3. **`ChatMessagePolicy`: cualquier staff puede ver/enviar en el chat de cualquier
   grupo.** Aproximación pragmática documentada en el propio código, por falta de
   `teacher_assignments` real todavía (la tabla existe en la migración
   `2027_01_01_000010`, pero la política no la usa). Sin commit en el rango. Lo
   único restringido hoy es `hide()`: exclusivo de `chat.moderate`.
4. **`private_chats.view.all` es deliberadamente de solo lectura**, nunca autoridad
   de escritura (Ley 1620 vs. Ley 1581). La justificación completa ya está en
   `config/permissions.php`, junto a la definición del permiso; **no se duplica
   aquí** — léela allí antes de tocar ese permiso o cualquier preset. Sin commit
   en el rango.
5. **Tipos de archivo permitidos en adjuntos:** PDF, imágenes (jpg/png/webp/gif) y
   Office sin macros (docx/xlsx/pptx). Sin SVG, HTML, ZIP ni formatos con macros.
   Commits de origen: `6511e3a` (imágenes, sin SVG) y `10f21df` (documentos, sin
   macros). **Aclaración:** ningún commit del rango toca "adjuntos de rúbricas"; lo
   que el rango cubre son los adjuntos de **entrega/evidencia** y el documento de
   retroalimentación del docente. Si existen adjuntos de rúbricas, su política de
   tipos no está respaldada por un commit de este rango.
6. **El adjunto de retroalimentación del docente es opcional** junto al texto:
   solo texto, solo archivo, o ambos; nunca obligatorio. Commit: `2e3593e`.
7. **dompdf se mantiene actualizado aunque no se usa todavía**, por si se
   implementan reportes en PDF más adelante. Pendiente de decisión final de
   Isabel. Commit de la actualización: `75212b4` (3.1.5 → 3.1.6). Una búsqueda en
   `app/`, `resources/` y `routes/` no encuentra ningún uso de dompdf.

---

## 3. Pendiente

Nada de esta sección tiene commit en el rango de la sección 1.

- **Sin estrategia de backup** para la base de datos ni para `storage/app`
  (evidencias de estudiantes, fotos, documentos de retroalimentación). Necesario
  antes del primer deploy con datos reales.
- **`TODO.md` y `bootstrap/app.php` tienen cambios sin commitear** desde antes de
  esta sesión (el bloque de `trustProxies`, más la entrada #35 de `TODO.md`).
  Pendiente decidir si van en un commit propio.
- **Revisar `trustProxies(at: ['127.0.0.1'])` en el primer deploy real a cPanel.**
  Está afinado para el demo con Cloudflare Quick Tunnel (cloudflared → Apache por
  loopback en la misma máquina); en cPanel la topología de proxy puede ser
  distinta o innecesaria. Ya advertido en el comentario de `bootstrap/app.php` y
  en `TODO.md` #35.
- **Checklist de deploy pendiente de ejecutar:**
  - Mover el proyecto fuera de `public_html`, exponiendo solo `public/`.
  - `APP_ENV=production`, `APP_DEBUG=false`.
  - `APP_URL` con `https`, `SESSION_SECURE_COOKIE=true`.
  - Usuario MySQL dedicado con contraseña.
  - `SEED_SUPER_ADMIN_PASSWORD` fuerte y rotada tras el primer ingreso.
    *Observado en el código:* `database/seeders/DatabaseSeeder.php` cae por
    defecto en `changeme123` si la variable no está definida — sin la variable,
    el super admin se crea con esa contraseña.
  - Permisos 755 en `storage/` y 600–640 en `.env`.
