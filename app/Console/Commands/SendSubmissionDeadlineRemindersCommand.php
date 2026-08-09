<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Communication\Actions\SendSubmissionDeadlineRemindersAction;
use Illuminate\Console\Command;

/**
 * Wrapper de consola de SendSubmissionDeadlineRemindersAction (Hito 5b),
 * programado a diario en routes/console.php. Requiere en producción un
 * cron `* * * * * php artisan schedule:run` -- todavía no existe en cPanel,
 * distinto del cron de queue:work ya confirmado (ver CLAUDE.md, sección
 * "Producción real"). Documentado en TODO.md como pendiente de Diego.
 */
class SendSubmissionDeadlineRemindersCommand extends Command
{
    protected $signature = 'reminders:send-deadlines';

    protected $description = 'Envía recordatorios de entrega a 3 y 1 día antes de la fecha límite de cada estudiante';

    public function handle(SendSubmissionDeadlineRemindersAction $action): int
    {
        $sent = $action->execute();

        $this->info("Recordatorios de entrega enviados: {$sent}");

        return self::SUCCESS;
    }
}
