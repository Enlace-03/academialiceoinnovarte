<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Events;

use App\Modules\Assessment\Models\Evaluation;
use Illuminate\Queue\SerializesModels;

/**
 * Primer evento de dominio real del proyecto (Hito 4, trabajo previo
 * obligatorio) -- disparado por EvaluateSubmissionAction, sin cambiar su
 * comportamiento existente. Tracking lo escucha para recalcular el
 * indicador cualitativo (nivel dominante), no el progress_pct mecánico
 * (eso lo dispara SubmissionRegistered).
 */
final class SubmissionEvaluated
{
    use SerializesModels;

    public function __construct(public readonly Evaluation $evaluation) {}
}
