# CLAUDE.md — Academia Liceo Innovarte

## Proyecto
Plataforma educativa ABP para Liceo Innovarte. Colombia. ~200 estudiantes, 1°-9°.
NO es un LMS tradicional — el concepto central es **Proyecto ABP**, no curso/lección.

## Stack
- Laravel 13 + PHP 8.3
- Filament 4 (paneles admin y académico)
- Livewire 3 + Alpine.js (paneles estudiante y padre)
- Tailwind CSS
- MySQL 9.1 (WampServer local, cPanel producción)
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
