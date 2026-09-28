<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Jobs;

use App\Models\User;
use App\Modules\Assessment\Actions\GenerateReportCardAction;
use App\Modules\Assessment\Models\ReportCard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use InvalidArgumentException;

/**
 * Cola 'database' (QUEUE_CONNECTION=database, sin Redis/Horizon). Recibe
 * student_id y type; la lógica vive en GenerateReportCardAction. Un solo
 * intento ($tries = 1): un boletín 'total' que ya existe falla a propósito
 * (hay que pedir $regenerate), y reintentar no lo arreglaría.
 * $generatedBy es opcional (quien lo pidió) para report_cards.generated_by.
 */
class GenerateReportCardJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $studentId,
        public readonly string $type,
        public readonly bool $regenerate = false,
        public readonly ?int $generatedBy = null,
    ) {
        if (! in_array($type, ReportCard::TYPES, true)) {
            throw new InvalidArgumentException("Tipo de boletín no válido: '{$type}'.");
        }
    }

    public function handle(GenerateReportCardAction $action): void
    {
        $action->execute(
            User::findOrFail($this->studentId),
            $this->type,
            $this->regenerate,
            $this->generatedBy !== null ? User::find($this->generatedBy) : null,
        );
    }
}
