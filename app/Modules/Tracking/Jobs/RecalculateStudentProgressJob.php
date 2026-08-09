<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Jobs;

use App\Models\User;
use App\Modules\Project\Models\Project;
use App\Modules\Tracking\Actions\RecalculateStudentProgressAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Encolado vía la cola 'database' ya configurada (QUEUE_CONNECTION=database,
 * sin Redis/Horizon -- regla absoluta del proyecto). Un job por
 * estudiante+proyecto, disparado por los Listeners de Tracking (uno por
 * evento) y, en lote, por el botón "Recalcular todo ahora".
 */
class RecalculateStudentProgressJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly User $student,
        public readonly Project $project,
    ) {}

    public function handle(RecalculateStudentProgressAction $action): void
    {
        $action->execute($this->student, $this->project);
    }
}
