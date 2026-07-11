---
name: migration-conventions
description: Cargar esta skill cuando se creen o modifiquen migraciones. Cubre las restricciones específicas de MySQL 9.1 en WampServer, el límite de longitud de índices, y las reglas de la tabla learning_events.
---

# Convenciones de migraciones — Liceo Innovarte

## MySQL 9.1 en WampServer — restricciones reales

El entorno local tiene MySQL 9.1 con `ROW_FORMAT=COMPACT` por defecto.
Esto limita los índices a **1000 bytes** (no 3072 como en DYNAMIC).

### Regla práctica: limitar strings en índices

Toda columna `string` que participe en un índice debe tener longitud máxima explícita:

```php
// ✅ CORRECTO — longitud explícita pequeña
$table->string('status', 20)->default('open');
$table->string('level', 20);
$table->string('avatar_key', 30);
$table->string('context_route', 80);
$table->index(['status', 'level']); // 20 + 20 = 40 bytes ✓

// ❌ INCORRECTO — string sin longitud (por defecto 255, supera el límite)
$table->string('status')->default('open');
$table->string('context_route');
$table->index(['status', 'context_route']); // 255 + 255 = 510 bytes → FALLA
```

### Longitudes recomendadas por tipo de dato

| Dato | Longitud máxima | Ejemplo |
|---|---|---|
| Estado/status | 20 | `open`, `resolved`, `not_started` |
| Nivel/level | 20 | `low`, `medium`, `high`, `critical` |
| Clave de avatar | 30 | `rectora_isabel`, `mentor_nino` |
| Ruta de contexto | 80 | `projects.show`, `dashboard.parent` |
| Rol | 20 | `student`, `teacher`, `rector` |
| Clave de paso | 60 | `welcome`, `first_project_created` |
| Tipo de evento | 50 | `guide_completed`, `submission_created` |
| Versión de modelo | 30 | `rules-v0`, `ml-v1.2` |

### NO usar DB::raw() en índices en Laravel 13

Laravel 13 no acepta `DB::raw()` dentro de `unique()` o `index()` con Blueprint.
La solución es definir la longitud en la columna, no en el índice.

```php
// ❌ FALLA en Laravel 13
$table->index([DB::raw('status(20)'), DB::raw('level(20)')]);

// ✅ CORRECTO
$table->string('status', 20);
$table->string('level', 20);
$table->index(['status', 'level']);
```

## Reglas generales

- Tablas en inglés, snake_case, plural
- `school_grades` = grados escolares. NUNCA `grades` solo.
- `softDeletes()` en: projects, phases, guides, resources, expected_evidences, submissions, observations
- Todo `down()` debe funcionar y probarse con `migrate:rollback`
- Timestamps en UTC siempre
- UUID en recursos que aparecen en URLs: `$table->uuid('uuid')->unique()`
- `AppServiceProvider::boot()` tiene `Schema::defaultStringLength(191)` — no remover

## Tabla learning_events (caso especial)

En **local** (WampServer): sin particionado.
En **producción** (cPanel): con particionado mensual.

La migración local usa esta versión:
```php
DB::statement(<<<'SQL'
    CREATE TABLE learning_events (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        student_id BIGINT UNSIGNED NOT NULL,
        project_id BIGINT UNSIGNED NULL,
        event_type VARCHAR(50) NOT NULL,
        payload JSON NULL,
        occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id, occurred_at),
        KEY idx_student_project_time (student_id, project_id, occurred_at),
        KEY idx_type_time (event_type, occurred_at)
    ) ENGINE=InnoDB ROW_FORMAT=DYNAMIC
SQL);
```

Reglas que aplican en ambos entornos:
- **Sin foreign keys** — nunca agregar FKs a esta tabla
- PK compuesta: `(id, occurred_at)`
- Cambios de esquema con `DB::statement()` crudo, nunca con Blueprint
- No modificar con `Schema::table()` — usar `ALTER TABLE` crudo

## Checklist antes de crear una migración

- [ ] ¿Los strings en índices tienen longitud ≤ 100?
- [ ] ¿El `down()` está completo y funciona?
- [ ] ¿Las entidades académicas tienen `softDeletes()`?
- [ ] ¿Los recursos en URLs tienen columna `uuid`?
- [ ] ¿La tabla tiene `timestamps()`?
- [ ] ¿No estoy modificando `learning_events` con Blueprint?

## Probar después de crear

```bash
php artisan migrate          # aplicar
php artisan migrate:rollback # verificar que down() funciona
php artisan migrate          # volver a aplicar
```
