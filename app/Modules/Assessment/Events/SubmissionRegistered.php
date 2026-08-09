<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Events;

use App\Modules\Assessment\Models\Submission;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado por RegisterSubmissionAction, sin cambiar su comportamiento
 * existente. Se dispara igual en creación y en re-entrega (updateOrCreate
 * sobre la misma fila, decisión ya confirmada del Hito 2) -- Tracking
 * recalcula igual en ambos casos, es idempotente.
 */
final class SubmissionRegistered
{
    use SerializesModels;

    public function __construct(public readonly Submission $submission) {}
}
