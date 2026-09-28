<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Policies;

use App\Models\User;

/**
 * Quién puede ver/descargar el boletín de un estudiante. Se autoriza contra
 * el ESTUDIANTE, no contra una fila de report_cards:
 * Gate::authorize('view', [ReportCard::class, $student]).
 *
 *  - Acudiente del estudiante: sí.
 *  - Staff con report_cards.view (rector, coordinator, teacher): sí.
 *  - Cualquier otro caso: no -- incluido el PROPIO estudiante (el boletín
 *    llega a la familia, no se descarga desde la cuenta del menor), un
 *    acudiente de otro estudiante y secretary (no tiene el permiso).
 *
 * Igual que ChatMessagePolicy: para el docente es "cualquier docente" y no
 * "docente de este estudiante" porque teacher_assignments (teacher_id,
 * subject_id, group_id) existe en la base pero es scaffolding huérfano, sin
 * Model ni datos reales (ver TODO.md), así que no se puede acotar a "mis
 * estudiantes" con precisión todavía. Más abierto de lo ideal, pero es
 * personal del colegio, no terceros; revisar cuando exista una asignación
 * docente real.
 */
class ReportCardPolicy
{
    public function view(User $user, User $student): bool
    {
        if ($user->is($student)) {
            return false;
        }

        if ($user->hasRole('parent')) {
            return $user->isGuardianOf($student);
        }

        return $user->hasPermissionTo('report_cards.view');
    }
}
