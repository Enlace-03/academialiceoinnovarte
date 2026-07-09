---
name: permissions-conventions
description: Use this skill whenever working with roles, permissions, user creation, permission delegation, or authorization in the Liceo Innovarte project. Covers the delegation ceiling, scoped permissions, and the difference between fixed roles (student/parent) and permission-based staff.
---

# Sistema de permisos — Liceo Innovarte

## Modelo

- **Personal** (admin, rector, coordinador, secretario, teacher): permisos ATÓMICOS
  marcables individualmente. Los roles-preset solo precargan casillas; la autoridad real
  son los permisos directos del usuario.
- **Estudiantes y padres**: roles FIJOS uniformes (`student`, `parent`). Nunca se
  configuran permiso por permiso. Su acceso se controla por middleware de ruta
  (`role:student`, `role:parent`) y scoping a `auth()->id()` / relación `parent_student`.

## Fuente de verdad

`config/permissions.php` — catálogo de permisos, presets y roles fijos.
Nunca hardcodear nombres de permisos fuera de este archivo o de constantes derivadas de él.

## Las tres reglas de oro

### 1. Techo de delegación (estricto)

Nadie puede otorgar un permiso que él mismo no tiene. Se valida SIEMPRE en el servidor,
en `AssignPermissionsAction`, no solo en la UI. Excepción: `admin` puede otorgar todo.

```php
// La Action rechaza cualquier permiso fuera del conjunto del otorgante.
app(AssignPermissionsAction::class)->execute($granter, $target, $permissions, $scoped);
```

### 2. users.create y users.grant son permisos, no roles

Cualquier usuario puede tenerlos: Diego, Isa, un futuro coordinador o secretario.
- `users.create` → puede crear usuarios.
- `users.grant` → puede delegar permisos a otros.
Tener uno no implica el otro.

### 3. Permisos con alcance (scope)

`students.create.scoped` lleva un scope en `user_grants`:
- `{"type": "all"}` → cualquier estudiante de la institución.
- `{"type": "groups", "group_ids": [3,5]}` → solo esos grupos.

El rector define el scope al conceder. La `StudentPolicy::create($user, $groupId)`
verifica el scope contra el grupo destino.

## Al crear pantallas de gestión de usuarios (Filament)

- La lista de casillas de permisos debe FILTRARSE a los permisos que tiene el usuario
  autenticado (el otorgante). Nunca mostrar permisos que él no puede dar.

```php
// En el UserResource form, filtrar opciones al conjunto del otorgante:
CheckboxList::make('permissions')
    ->options(function () {
        $granter = auth()->user();
        return collect(config('permissions.catalog'))
            ->flatMap(fn ($perms) => $perms)
            ->filter(fn ($label, $key) =>
                $granter->hasRole('admin') || $granter->hasPermissionTo($key))
            ->toArray();
    });
```

- Para `students.create.scoped`, mostrar un selector de alcance (todos / grupos)
  cuando se marque ese permiso. El selector de grupos se limita a los grupos que el
  otorgante puede delegar.

## Al autorizar acciones

- Siempre vía Policy (`$user->can(...)`), nunca chequeando strings de rol.
- Filament respeta las Policies automáticamente si están registradas en `AuthServiceProvider`.
- Para acciones scopeadas, pasar el contexto: `$user->can('create', [Student::class, $groupId])`.

## Qué NO hacer

- No confiar en el filtrado de la UI para el techo de delegación: validar en la Action.
- No crear permisos nuevos fuera de `config/permissions.php`.
- No dar permisos del catálogo a estudiantes o padres.
- No permitir self-signup: los usuarios siempre los crea alguien con `users.create`.
