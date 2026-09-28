<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Events;

use App\Modules\Assessment\Models\ReportCard;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado por GenerateReportCardAction una vez guardado el PDF (nuevo o
 * regenerado). Sin listeners por ahora: queda como punto de enganche para
 * notificar a la familia más adelante, sin decidir aún quién lo dispara ni
 * a quién avisa.
 */
final class ReportCardGenerated
{
    use SerializesModels;

    public function __construct(public readonly ReportCard $reportCard) {}
}
