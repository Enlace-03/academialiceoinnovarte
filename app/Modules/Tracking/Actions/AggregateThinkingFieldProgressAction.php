<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Actions;

use App\Models\User;
use App\Modules\Institution\Models\ThinkingField;
use App\Modules\Project\Models\Project;
use Illuminate\Support\Collection;

/**
 * Progreso agregado por campo de pensamiento (Hito 4b, Analytics) --
 * anticipado explícitamente en el docblock de StudentProgressRelationManager
 * ("El dashboard agregado por campo de pensamiento queda fuera de este
 * hito"). Solo la barra mecánica agregada -- sin nivel cualitativo agregado
 * por campo, eso queda diferido al hito de Boletines (ver TODO.md).
 *
 * "Proyecto activo" = todos los que el estudiante puede acceder (mismo
 * criterio que MyProjects::projects(): cycle_id del estudiante + status
 * published) -- no existe hoy ninguna otra noción de "activo" en el
 * proyecto (confirmado revisando RecalculateStudentProgressAction y
 * StudentProgress antes de asumir cualquier otra cosa). Un proyecto en
 * draft NO cuenta, mismo aislamiento que ya exige el hito de borrador/
 * publicado recién cerrado.
 *
 * Cálculo: promedio simple (no ponderado por duración ni cantidad de
 * evidencias) de progress_pct entre los proyectos que tocan cada campo.
 * Lee de student_progress ya precalculado (fila project-level, phase_id
 * null) -- nunca recalcula nada en vivo, mismo criterio que el resto de
 * Tracking ("Datos: siempre de métricas precalculadas").
 *
 * Un proyecto que toca el campo pero para el cual el estudiante todavía no
 * tiene fila en student_progress (cero submissions/foro/chat, ningún
 * listener disparó RecalculateStudentProgressAction todavía) cuenta como
 * 0% -- es información real ("no ha empezado ESE proyecto"), a diferencia
 * de un campo sin NINGÚN proyecto que lo toque, que se omite por completo
 * (nunca se muestra como 0%, sería información falsa: "va mal" en vez de
 * "no aplica todavía").
 */
final class AggregateThinkingFieldProgressAction
{
    /**
     * @return Collection<int, array{thinkingField: ThinkingField, progressPct: int}>
     */
    public function execute(User $student): Collection
    {
        $cycleId = $student->schoolGrade?->cycle_id;

        if ($cycleId === null) {
            return new Collection();
        }

        $projects = Project::query()
            ->where('cycle_id', $cycleId)
            ->where('status', 'published')
            ->with([
                'thinkingFields',
                'studentProgress' => fn ($query) => $query->where('student_id', $student->id),
            ])
            ->get();

        return ThinkingField::query()
            ->orderBy('order')
            ->get()
            ->map(function (ThinkingField $field) use ($projects): ?array {
                $touchingProjects = $projects->filter(
                    fn (Project $project): bool => $project->thinkingFields->contains('id', $field->id)
                );

                if ($touchingProjects->isEmpty()) {
                    return null;
                }

                $averagePct = (int) round(
                    $touchingProjects->avg(
                        fn (Project $project): int => $project->studentProgress->first()->progress_pct ?? 0
                    )
                );

                return ['thinkingField' => $field, 'progressPct' => $averagePct];
            })
            ->filter()
            ->values();
    }
}
