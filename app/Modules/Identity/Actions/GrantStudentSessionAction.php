<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Models\StudentSessionGrant;
use App\Modules\Institution\Models\Group;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Auth-switch real (Hito 3b-2, decisión ya confirmada tras la auditoría
 * previa): el docente queda deslogueado de /academia en este mismo
 * dispositivo -- es el comportamiento deseado, no un efecto secundario a
 * evitar. Registra la entrega ANTES de cambiar de identidad (para que
 * quede constancia aunque algo falle después), y guarda el id de la
 * entrega en la sesión NUEVA (después de loginUsingId, que regenera la
 * sesión) para que GroupChat/ProjectShow/ForumThreadShow y el middleware de
 * expiración sepan de qué entrega se trata sin adivinar por heurística.
 */
final class GrantStudentSessionAction
{
    public function execute(
        User $grantedBy,
        User $student,
        Group $group,
        ?string $ipAddress,
        ?string $userAgent,
    ): StudentSessionGrant {
        if ($student->group_id !== $group->id) {
            throw ValidationException::withMessages([
                'student_id' => 'El estudiante no pertenece a este grupo.',
            ]);
        }

        $grant = StudentSessionGrant::create([
            'student_id' => $student->id,
            'granted_by_user_id' => $grantedBy->id,
            'group_id' => $group->id,
            'started_at' => now(),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);

        Auth::loginUsingId($student->id);

        session([
            'active_grant_id' => $grant->id,
            'active_grant_last_seen_at' => now()->toISOString(),
        ]);

        return $grant;
    }
}
