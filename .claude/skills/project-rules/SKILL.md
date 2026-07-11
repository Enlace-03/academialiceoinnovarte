---
name: project-rules
description: Cargar esta skill cuando haya dudas sobre decisiones de arquitectura, stack, o restricciones del proyecto. Contiene todas las decisiones tomadas y el porqué de cada una.
---

# Decisiones de arquitectura — Liceo Innovarte

## Decisiones de stack (no reabrir)

| Decisión | Elegido | Descartado | Razón |
|---|---|---|---|
| Base de datos | MySQL 9.1 | PostgreSQL | Restricción del hosting cPanel |
| UI admin/académico | Filament 4 | Nova, custom | Dev único — Filament da el 70% gratis |
| UI estudiante/padre | Livewire 3 + Blade | Filament, Vue | UI diferente a admin-like, más amigable |
| Colas | Database driver | Redis, Horizon | Sin Redis en hosting |
| Cache | Database driver | Redis, Memcached | Sin Redis en hosting |
| Deploy | Rama `deploy` con vendor/ comiteado | CI/CD, SSH | Sin SSH en cPanel |
| Autenticación | Laravel Auth + Filament | Sanctum (API) | No hay frontend separado |
| Roles y permisos | Spatie Permission + permisos atómicos | Roles rígidos | Delegación configurable por rector |

## Arquitectura: monolito modular

Un solo proyecto Laravel. Los módulos viven en `app/Modules/`. No microservicios.
Extracción a servicio separado solo cuando un módulo cause problemas reales de escala.

## Separación UI / Dominio

```
Filament Resource          →  llama a  →  Module Action
Livewire Component         →  llama a  →  Module Action
Module Action              →  dispara  →  Evento de dominio
Evento de dominio          →  activa   →  Listeners (Tracking, Prediction, Communication)
```

Nunca: Filament → BD directo. Nunca: Livewire → lógica de negocio.

## Entorno local vs producción

| Aspecto | Local (WampServer) | Producción (cPanel) |
|---|---|---|
| MySQL | 9.1, sin particionado en learning_events | 8.x, con particionado |
| PHP | 8.3 vía WampServer | 8.3 vía MultiPHP |
| Cron | No aplica | `schedule:run` cada minuto |
| Assets | `npm run dev` (Vite) | `npm run build` comiteado en deploy |

La migración `000050` tiene una versión local (sin particionado) y la rama `deploy`
tendrá la versión de producción (con particionado).

## Permisos: modelo delegable

- Personal (admin, rector, etc.): permisos atómicos marcables
- Estudiantes y padres: roles fijos uniformes (student, parent)
- Techo de delegación: validado en servidor en AssignPermissionsAction
- Fuente de verdad: `config/permissions.php`

## Convenciones de naming

- Tablas: inglés, snake_case, plural
- `school_grades` = grados escolares (nunca `grades` solo — ambiguo)
- `gradings` o `evaluation_results` = calificaciones/evaluaciones
- Variables y métodos PHP: camelCase
- Rutas: kebab-case
- Componentes Blade: kebab-case (`<x-rubric-badge />`)
- Componentes Livewire: PascalCase en clase, kebab en template

## Restricciones por datos de menores

- UUIDs en todas las URLs que exponen recursos de estudiantes
- No self-signup — solo admin o rector crean cuentas
- No enviar PII a servicios externos (LLMs, analytics)
- Política de tratamiento de datos requerida antes del piloto real
