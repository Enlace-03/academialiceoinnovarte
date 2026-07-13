# UserResource con techo de delegación — Academia Liceo Innovarte

Estos archivos siguen la estructura estándar de recursos de **Filament 4**
(carpeta por recurso, con `Schemas/` y `Tables/` separados de la clase
principal). Cópialos dentro de tu proyecto Laravel respetando las rutas
relativas — ya vienen en la posición correcta (`app/...`, `database/...`).

## Cómo funciona el "techo de delegación"

La idea central: **nadie puede otorgar más autoridad de la que él mismo tiene.**

Se implementa con dos reglas independientes:

1. **Techo para roles** — se agrega una columna `level` (entero) a la tabla
   `roles` de Spatie Permission. Un usuario solo puede asignar roles cuyo
   `level` sea **menor o igual** a su propio nivel máximo (`maxRoleLevel()`).
   Esto es inclusivo a propósito: dos Coordinadores del mismo nivel sí pueden
   crearse mutuamente, pero nadie puede crear a alguien por encima de sí mismo.

2. **Techo para permisos individuales** — no necesitan un nivel numérico:
   la regla es que **solo puedes delegar un permiso que tú mismo posees**
   (directo o heredado de tus roles), vía `getAllPermissions()` de Spatie.

Un rol `Super Admin` (constante `HasDelegationCeiling::SUPER_ADMIN_ROLE`)
bypasea el techo por completo y puede asignar cualquier rol o permiso.

La validación ocurre en **dos capas**:
- En el formulario: las opciones visibles de roles/permisos ya vienen
  filtradas (`modifyQueryUsing`), así el usuario ni siquiera ve lo que no
  puede otorgar.
- En el servidor: `WithinDelegationCeiling` vuelve a validar los IDs
  enviados al guardar, por si el request se manipula directamente. Sin esta
  segunda capa, el filtro de la UI sería solo cosmético.

Adicionalmente, `canManageUser()` en el trait controla si un usuario puede
**editar o eliminar** a otro (no solo asignarle roles): nadie puede tocar la
cuenta de alguien con igual o mayor nivel que él, salvo Super Admin. Esto lo
usa `UserPolicy`.

## Supuestos que debes ajustar

- **Nombres de rol** en `RoleLevelSeeder`: usé `Super Admin`, `Rectora`,
  `Coordinador Académico`, `Coordinador Administrativo`, `Docente`,
  `Acudiente`, `Estudiante` como placeholders razonables para el contexto del
  colegio. Cámbialos por los nombres reales que ya tengas en tu
  `RoleSeeder`/`PermissionSeeder`.
- **Nombres de permisos** en `UserPolicy`: asumí la convención
  `users.view`, `users.create`, `users.update`, `users.delete`. Ajusta si usas
  otra.
- **Campos del modelo**: asumí que `users` tiene `is_active` y
  `document_number`. Si no existen, quítalos del formulario o agrega las
  columnas correspondientes.
- Asumí que ya tienes un mecanismo tipo `Gate::before` para que Super Admin
  bypasee las policies de Laravel en general (patrón típico con Spatie). Si
  no lo tienes, agrégalo en `AppServiceProvider`:

  ```php
  use Illuminate\Support\Facades\Gate;

  Gate::before(fn ($user, $ability) => $user->hasRole('Super Admin') ? true : null);
  ```

## Pasos de integración

1. Copia los archivos a las rutas equivalentes en tu proyecto.

2. Corre la migración:
   ```bash
   php artisan migrate
   ```

3. Ajusta los nombres de rol en `RoleLevelSeeder.php` y ejecútalo (o
   intégralo a tu `DatabaseSeeder`):
   ```bash
   php artisan db:seed --class=RoleLevelSeeder
   ```

4. En tu modelo `app/Models/User.php`, agrega el trait junto al `HasRoles`
   de Spatie que ya debes tener:
   ```php
   use App\Models\Concerns\HasDelegationCeiling;
   use Spatie\Permission\Traits\HasRoles;

   class User extends Authenticatable
   {
       use HasRoles;
       use HasDelegationCeiling;
       // ...
   }
   ```

5. Registra el policy en `AppServiceProvider` (o donde registres policies),
   si tu Laravel 13 no lo auto-descubre:
   ```php
   use App\Models\User;
   use App\Policies\UserPolicy;

   Gate::policy(User::class, UserPolicy::class);
   ```

6. Registra `UserResource` en tu panel `/admin` (si no usa auto-discovery de
   recursos, agrégalo a `AdminPanelProvider`).

7. **Bootstrapping**: como nadie tiene techo hasta que exista el primer
   Super Admin, crea ese primer usuario por seeder o `tinker`, no desde la
   UI:
   ```php
   $diego = User::factory()->create([...]);
   $diego->assignRole('Super Admin');
   ```

## Nota sobre el panel `/academia`

Este recurso vive en `/admin`, pensado para que Diego e Isa gestionen
usuarios del sistema completo. Si más adelante Rafa (u otro coordinador)
necesita gestionar solo Docentes/Estudiantes desde `/academia`, el mismo
trait `HasDelegationCeiling` funciona ahí también — bastaría con crear un
`UserResource` más acotado en ese panel (filtrando a roles con `level` bajo,
por ejemplo) reutilizando exactamente la misma lógica de techo.
