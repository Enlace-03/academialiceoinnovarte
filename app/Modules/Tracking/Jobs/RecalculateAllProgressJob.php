<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Jobs;

use App\Models\User;
use App\Modules\Project\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado solo manualmente por el botón "Recalcular todo ahora" (nunca
 * automático -- decisión confirmada del Hito 4). "Proyectos activos" =
 * todos los no eliminados (softDeletes ya los excluye por defecto); no hay
 * ningún otro concepto de "activo" en el esquema de Project hoy.
 * "Estudiantes" de cada proyecto = los que canAccessProject() autorizaría
 * (mismo grado->ciclo que el proyecto), consultado directo por eficiencia
 * en vez de instanciar la Policy por cada fila.
 *
 * Fan-out: un RecalculateStudentProgressJob por par estudiante+proyecto, no
 * un solo job gigante -- más resiliente a fallos parciales y no bloquea la
 * cola con una transacción larga.
 */
class RecalculateAllProgressJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Project::query()->with('cycle')->chunk(50, function ($projects) {
            foreach ($projects as $project) {
                $students = User::query()
                    ->role('student')
                    ->whereHas('schoolGrade', fn ($query) => $query->where('cycle_id', $project->cycle_id))
                    ->get();

                foreach ($students as $student) {
                    RecalculateStudentProgressJob::dispatch($student, $project);
                }
            }
        });
    }
}
