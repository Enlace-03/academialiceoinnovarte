# CLAUDE.md — Academia Liceo Innovarte

## Proyecto
Plataforma educativa ABP para Liceo Innovarte. Colombia. ~200 estudiantes, 1°-9°.
NO es un LMS tradicional — el concepto central es **Proyecto ABP**, no curso/lección.

## Stack
- Laravel 13 + PHP 8.3
- Filament 4 (paneles admin y académico)
- Livewire 3 + Alpine.js (paneles estudiante y padre)
- Tailwind CSS
- MySQL 9.1 (WampServer local) | **MariaDB 10.11.18** (cPanel producción — verificado 2026-08-07, ver sección "Producción real")
- Colas: database | Cache: database | Sin Redis, sin SSH

## Paneles
| Panel | Ruta | Roles | Tecnología |
|---|---|---|---|
| Admin | `/admin` | admin | Filament — azul |
| Académico | `/academia` | rector, teacher | Filament — verde |
| Estudiante | `/` | student | Livewire + Blade |
| Padre | `/familia` | parent | Livewire + Blade |

## Estructura de carpetas clave
```
app/
├── Filament/Admin/         # Resources, Pages, Widgets del admin
├── Filament/Academic/      # Resources, Pages, Widgets del académico
├── Livewire/Student/       # Componentes del estudiante
├── Livewire/Parent/        # Componentes del padre
├── Livewire/Shared/        # AvatarDock, RubricBadge, ChatBox
└── Modules/                # Dominio puro (sin UI)
    ├── Identity/           # users, perfiles, roles, permisos
    ├── Institution/        # institución, grados, grupos
    ├── Project/            # proyectos ABP, fases, guías, recursos
    ├── Assessment/         # entregas, rúbricas, evaluaciones
    ├── Community/          # foros, chat
    ├── Tracking/           # progreso, métricas, learning_events
    ├── Analytics/          # dashboards, KPIs
    ├── Prediction/         # reglas de riesgo, alertas
    ├── Avatar/             # 4 avatares, mensajes, onboarding
    ├── Communication/      # notificaciones
    └── Shared/             # enums, DTOs, helpers
```

## Reglas absolutas (nunca violar)
1. Filament Resources y Livewire Components NO contienen lógica de negocio → va en Actions
2. Todo hecho significativo dispara un evento de dominio → listeners reaccionan
3. `learning_events` NO tiene foreign keys (tabla particionada)
4. Niveles de rúbrica NUNCA se muestran como números → siempre texto + color
5. UUIDs en URLs de estudiantes → nunca IDs autoincrementales
6. No self-signup → solo admin o rector crean cuentas
7. No Redis, no Horizon, no supervisord → colas en BD, workers con `--stop-when-empty`
8. Techo de delegación: nadie otorga permisos que no tiene → validar en AssignPermissionsAction

## Skills disponibles (cargar según tarea)
- `project-rules` — decisiones de arquitectura tomadas
- `filament-conventions` — crear Resources, Widgets, Actions de Filament
- `module-generator` — crear módulos de dominio o decidir Filament vs Livewire
- `migration-conventions` — MySQL 9.1, índices, learning_events
- `permissions-conventions` — sistema de permisos delegables
- `livewire-components` — panel estudiante y padre
- `abp-domain` — conceptos del dominio ABP de Liceo Innovarte
- `rubric-evaluation` — evaluación cualitativa de 4 niveles
- `testing-standards` — Pest, qué testear
- `git-workflow` — flujo main/deploy para cPanel

## Regla absoluta: comandos destructivos de base de datos

Nunca ejecutar `migrate:fresh`, `migrate:rollback`, `db:wipe`, `TRUNCATE`, `DROP TABLE`,
o cualquier comando que borre datos, contra ninguna base (dev o testing), sin antes
pegarle a Diego el comando exacto y esperar su confirmación explícita. Sin excepción
de urgencia, y sin excepción "solo quiero verificar algo rápido". Verificar dos veces
`--env=` y `DB_DATABASE` antes de proponer el comando: el `.env` por defecto apunta a
la base de **desarrollo**, no a testing — un `migrate:fresh --force` sin `--env=testing`
borra datos reales de dev. (Incidente: 2026-08-07, hito 1C — se restauró vía seeders.)

## Regla absoluta: nada de Google Admin Console real

No usar la extensión de navegador ni ninguna forma de exploración semi-autónoma
contra la Google Admin Console real del colegio, bajo ninguna circunstancia —
ni siquiera "solo para revisar opciones". Gestiona identidad y datos de todo el
colegio (menores incluidos), no es un entorno de desarrollo. Cualquier
configuración ahí (cuenta de servicio, delegación a nivel de dominio, unidades
compartidas) la hace la rectora misma como super administradora, siguiendo un
instructivo preparado de antemano. Ver TODO.md #12 (integración de Google
Workspace, diferida hasta confirmar licencia permanente) para el contexto
completo y el diseño recomendado.

## Producción real (cPanel) — verificado antes del Hito de Tracking

Verificación hecha en vivo contra el cPanel y la base de datos reales de producción
(`cpanel.liceoinnovarte.edu.co`) el 2026-08-07, antes de que `learning_events` y las
colas de trabajo se vuelvan necesarias. Todo lo de abajo es un hecho confirmado, no
una suposición — cualquier cambio de plan de hosting debe re-verificarse.

| # | Punto | Estado | Detalle |
|---|---|---|---|
| 1 | Colas (cron) | ✅ confirmado | `Cron Jobs` disponible en cPanel (Avanzada), formulario estándar minuto/hora/día/mes/día-semana, sin límite visible. Actualmente sin ningún cron configurado. Comando de ejemplo del propio cPanel: `/usr/local/bin/ea-php83 /home/liceoinnovarteed/academia.liceoinnovarte.edu.co/artisan queue:work --stop-when-empty` cada minuto (`* * * * *`). |
| 2 | Tabla particionada (`learning_events`) | ✅ confirmado | Probado en vivo: se creó una BD temporal (`liceoinnovarteed_parttest`, borrada al terminar), y dentro una tabla con `PARTITION BY RANGE (UNIX_TIMESTAMP(...))` con 3 particiones — funcionó sin error de privilegios. También se probó `ALTER TABLE ... REORGANIZE PARTITION pmax INTO (...)` (lo que necesita el comando `events:archive`) — funcionó igual. El motor real es **MariaDB 10.11.18**, no MySQL — sintaxis de particionado compatible, pero cualquier función específica de MySQL debe verificarse contra MariaDB antes de usarse. |
| 3 | Compilación de assets | ✅ ya resuelto, sin ajuste necesario | El skill `git-workflow` ya documentaba correctamente que `npm run build` y `composer install --no-dev` corren **en local**, y los artefactos compilados (`vendor/`, `public/build/`, `public/css|js/filament`) se comitean a la rama `deploy` con `git add -f`. cPanel solo hace `Git Version Control → Update from Remote`. No compila nada — no hay riesgo de que falte Node.js en el servidor. |
| 4 | Versión de PHP / Composer | ✅ confirmado (reconfirmado 2026-08-09, ver nota abajo) | `MultiPHP Manager`: versión activa **PHP 8.3 (ea-php83)** en ambos dominios (`academia.liceoinnovarte.edu.co` y `liceoinnovarte.edu.co`, ambos en "Inherited"). Versiones disponibles hasta PHP 8.5. Cumple `"php": "^8.3"` de `composer.json`. `composer install` corre en local (mismo hallazgo que el punto 3) — el límite de memoria del plan no es un riesgo porque Composer nunca corre en el servidor. |
| 5 | Almacenamiento en disco | ✅ confirmado | Cuota total del plan: **2.44 GB** (18.58 MB usados hoy, 0.74%). Cuota de bases de datos separada: **2.42 GB** (0 bytes usados — **todavía no existe ninguna base de datos de la app en producción**, el deploy real está pendiente). Ancho de banda: 48.83 GB/mes (0.08% usado). Límite de bases de datos del plan: **2 en total**. |
| 6 | `memory_limit` de PHP | ✅ confirmado 2026-08-09 | `MultiPHP INI Editor` → dominio `academia.liceoinnovarte.edu.co`: **`memory_limit = 1G`** (también `post_max_size` y `upload_max_filesize` en `1G`). Verificado para el Hito de Galería (compresión de imágenes con Intervention Image/GD, que decodifica el bitmap completo en RAM) — con margen amplio sobre los 512M que `CompressUploadedImageAction` intenta pedir vía `ini_set()`; ver ese Action para la estrategia completa (incluye un chequeo de dimensiones antes de decodificar, que rechaza con un error de validación en vez de crashear si algún día el límite real resultara insuficiente). |

**Hallazgo adicional (no solicitado, relevante para revisar después):** la sección
"SSH Access" de cPanel muestra una interfaz completa de gestión de llaves SSH
(generar/importar/administrar), lo que sugiere que el SSH real podría estar
disponible en este plan — no se probó una conexión real. Si se confirma, cambia
las opciones del flujo de deploy (Opción A de "Manejo de migraciones sin SSH" en
el skill `git-workflow` podría dejar de ser condicional).

**Falsa alarma, resuelta el 2026-08-09:** al verificar el punto 6 (`memory_limit`)
contra `MultiPHP INI Editor` para `academia.liceoinnovarte.edu.co`, esa pantalla
mostró `PHP Version ea-php82 (Inherited)`, contradiciendo el punto 4 de esta misma
tabla (`ea-php83`, verificado 2026-08-07). Diego reconfirmó contra `MultiPHP
Manager` con captura directa: el sistema corre **PHP 8.3 (ea-php83)** como versión
por defecto, y tanto `academia.liceoinnovarte.edu.co` como `liceoinnovarte.edu.co`
lo heredan correctamente — el punto 4 estaba bien desde el 2026-08-07, no hubo
ningún cambio real de versión. **`MultiPHP INI Editor` no es la fuente confiable
para verificar la versión de PHP activa de un dominio — `MultiPHP Manager` sí lo
es.** El "ea-php82" que mostró el INI Editor es, hasta donde se sabe, un dato
mostrado de forma inconsistente por esa pantalla puntual, no un reflejo de la
configuración real. Ningún cambio de código ni de plan de deploy fue necesario.

**Nota:** no hay ninguna base de datos de la aplicación en producción todavía —
el primer deploy real sigue pendiente. El número de bases de datos del plan (2)
es ajustado: la base de datos de la app consume una, dejando una sola de margen.

## Comandos frecuentes
```bash
php artisan migrate:fresh --seed    # reset completo
php artisan db:seed --class=RolePermissionSeeder
php artisan test
php artisan tinker
php artisan queue:work --stop-when-empty
php artisan metrics:recalculate
php artisan risk:evaluate
```
