<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\StudentSessionGrant;

/**
 * Cierre de una entrega de sesión (Hito 3b-2) -- lo usan dos caminos:
 * el botón "Terminar clase" (routes/web.php, /logout) y la expiración
 * automática por inactividad (App\Http\Middleware\ExpireDeliveredStudentSession).
 * Update directo (no fetch+save): idempotente si algo lo llama dos veces
 * (whereNull('ended_at') hace que la segunda llamada no toque nada).
 */
final class EndStudentSessionAction
{
    public function execute(int $grantId): void
    {
        StudentSessionGrant::whereKey($grantId)
            ->whereNull('ended_at')
            ->update(['ended_at' => now()]);
    }
}
