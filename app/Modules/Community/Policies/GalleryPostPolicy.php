<?php

declare(strict_types=1);

namespace App\Modules\Community\Policies;

use App\Models\User;
use App\Modules\Community\Models\GalleryPost;

/**
 * view() sirve a dos audiencias distintas con un solo método, mismo patrón
 * que ForumPostPolicy::view():
 * - Personal con gallery.publish o gallery.update.all ve todo (incluidas
 *   publicaciones ajenas), es el atajo "es personal, ve todo".
 * - Estudiante/acudiente: contenido general (project_id null) siempre
 *   visible; contenido de proyecto solo si el estudiante mismo, o alguno de
 *   los hijos del acudiente (User::children()), tiene acceso a ese proyecto
 *   vía User::canAccessProject() -- mismo aislamiento entre ciclos que ya
 *   usan ForumThreadPolicy/ForumPostPolicy/ProjectPolicy. Fail-closed: sin
 *   rol reconocido, no hay acceso.
 *
 * Esta es también la puerta que usan las rutas de servido de fotos
 * (galeria.fotos.show) -- ver routes/web.php -- así que gobierna tanto la
 * aparición del post en el listado como el acceso directo al archivo.
 */
class GalleryPostPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isGalleryStaff($user);
    }

    public function view(User $user, GalleryPost $post): bool
    {
        if ($this->isGalleryStaff($user)) {
            return true;
        }

        if ($post->project_id === null) {
            return true;
        }

        if ($user->hasRole('student')) {
            return $user->canAccessProject($post->project);
        }

        if ($user->hasRole('parent')) {
            return $user->children()->get()->contains(
                fn (User $child) => $child->canAccessProject($post->project),
            );
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('gallery.publish');
    }

    public function update(User $user, GalleryPost $post): bool
    {
        return $user->hasPermissionTo('gallery.update.all')
            || ($user->hasPermissionTo('gallery.update.own') && $post->created_by_user_id === $user->id);
    }

    public function delete(User $user, GalleryPost $post): bool
    {
        return $this->update($user, $post);
    }

    private function isGalleryStaff(User $user): bool
    {
        return $user->hasPermissionTo('gallery.publish') || $user->hasPermissionTo('gallery.update.all');
    }
}
