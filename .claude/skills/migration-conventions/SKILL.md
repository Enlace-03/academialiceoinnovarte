---
name: migration-conventions
description: Use this skill whenever creating or modifying database migrations in the Liceo Innovarte project. Contains MySQL-specific rules, naming conventions, and the special constraints of the learning_events partitioned table.
---

# Convenciones de migraciones — Liceo Innovarte

## Reglas generales

- MySQL 8, motor InnoDB. NO es PostgreSQL: no hay `jsonb` (usar `json`), no hay
  `timestamptz` (timestamps en UTC por convención de la app).
- Nombres de tablas y columnas en inglés, snake_case, plural para tablas.
- `school_grades` = grados escolares. La palabra `grades` sola está PROHIBIDA.
- Todo recurso en URLs lleva columna `uuid` única. Route model binding por uuid
  en rutas públicas/Livewire; Filament usa id internamente (protegido por auth).
- `softDeletes()` en entidades académicas.
- Todo `down()` debe funcionar.
- Enum de rúbrica: `['not_achieved', 'partially_achieved', 'achieved', 'exceeded']`.

## learning_events: tabla especial

- Particionada por rango mensual (`PARTITION BY RANGE (UNIX_TIMESTAMP(occurred_at))`).
- **NO admite foreign keys** — nunca agregar FKs.
- PK compuesta: `(id, occurred_at)`.
- Cambios de esquema con `DB::statement()` crudo.
- Naming de particiones: `pYYYYMM`.

## Datos de menores de edad

- Toda columna nueva con datos personales debe justificarse en docs/adr/.
- UUIDs en URLs siempre.
