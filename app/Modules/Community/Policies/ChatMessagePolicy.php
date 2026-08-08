<?php

declare(strict_types=1);

namespace App\Modules\Community\Policies;

use App\Models\User;
use App\Modules\Community\Models\ChatMessage;
use App\Modules\Institution\Models\Group;

/**
 * A diferencia del foro, chat_messages no tiene project_id ni una relación
 * de propiedad tipo ProjectPolicy — y teacher_assignments (teacher_id,
 * subject_id, group_id) existe en la base pero es scaffolding huérfano, sin
 * Model ni datos reales (ver TODO.md), así que no se puede acotar "el chat
 * de MIS grupos" con precisión para un docente todavía.
 *
 * Aproximación pragmática documentada: cualquier usuario de categoría staff
 * puede ver/enviar mensajes en el chat de cualquier grupo — más abierto de
 * lo ideal, pero es personal del colegio, no terceros. La única restricción
 * real hoy es hide(): exclusivo de chat.moderate (rector/coordinator), nunca
 * teacher ni ningún estudiante.
 */
class ChatMessagePolicy
{
    public function view(User $user, ChatMessage $message): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return ! $message->is_hidden && $user->group_id === $message->group_id;
    }

    public function create(User $user, Group $group): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return $user->group_id === $group->id;
    }

    public function hide(User $user, ChatMessage $message): bool
    {
        return $user->hasPermissionTo('chat.moderate');
    }
}
