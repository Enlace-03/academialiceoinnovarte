<?php

use App\Console\Commands\SendSubmissionDeadlineRemindersCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Hito 5b: requiere en producción un cron `* * * * * php artisan schedule:run`
// -- todavía no configurado en cPanel (distinto del cron de queue:work ya
// confirmado). Ver TODO.md.
Schedule::command(SendSubmissionDeadlineRemindersCommand::class)->dailyAt('07:00');
