<?php

declare(strict_types=1);

namespace App\Modules\Community\Policies;

use App\Models\User;
use App\Modules\Community\Models\PrivateChatThread;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTeam;
use App\Modules\Project\Policies\ProjectPolicy;

/**
 * "Docente responsable" = mismo criterio own/all que ya gobierna
 * ProjectPolicy::update() -- nunca "cualquier docente del colegio" (decisión
 * confirmada). Nota: coordinator/rector ya tienen projects.update.all en su
 * preset, así que canParticipate() los deja escribir también -- coincide
 * con el pedido explícito ("a menos que también tengan autoridad real sobre
 * el proyecto"), no es un descuido.
 *
 * viewContext()/create() reciben el contexto (proyecto + tipo + estudiante
 * o equipo) en vez de un PrivateChatThread real, porque el hilo puede no
 * existir todavía -- se crea recién con el primer mensaje (firstOrCreate en
 * SendPrivateChatMessageAction). view()/moderate() sí reciben el modelo,
 * una vez que ya existe.
 */
class PrivateChatThreadPolicy
{
    public function view(User $user, PrivateChatThread $thread): bool
    {
        return $this->canParticipate($user, $thread->project, $thread->type, $thread->student, $thread->team)
            || $user->hasPermissionTo('private_chats.view.all');
    }

    public function viewContext(User $user, Project $project, string $type, ?User $student, ?ProjectTeam $team): bool
    {
        return $this->canParticipate($user, $project, $type, $student, $team)
            || $user->hasPermissionTo('private_chats.view.all');
    }

    /**
     * Enviar mensaje: misma audiencia que viewContext(), EXCEPTO la
     * visibilidad institucional -- coordinator/rector con solo
     * private_chats.view.all pueden leer pero no escribir, a menos que
     * también tengan autoridad real sobre el proyecto (projects.update.*).
     */
    public function create(User $user, Project $project, string $type, ?User $student, ?ProjectTeam $team): bool
    {
        return $this->canParticipate($user, $project, $type, $student, $team);
    }

    public function moderate(User $user, PrivateChatThread $thread): bool
    {
        return $user->hasPermissionTo('private_chats.moderate');
    }

    private function canParticipate(User $user, Project $project, string $type, ?User $student, ?ProjectTeam $team): bool
    {
        if ($type === 'individual' && $student !== null && $user->id === $student->id) {
            return true;
        }

        if ($type === 'team' && $team !== null && $team->users()->whereKey($user->id)->exists()) {
            return true;
        }

        return app(ProjectPolicy::class)->update($user, $project);
    }
}
