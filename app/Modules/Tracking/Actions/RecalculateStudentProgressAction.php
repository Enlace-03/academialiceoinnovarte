<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Actions;

use App\Models\User;
use App\Modules\Assessment\Models\EvaluationResult;
use App\Modules\Assessment\Models\RubricLevel;
use App\Modules\Assessment\Models\Submission;
use App\Modules\Community\Models\ChatMessage;
use App\Modules\Community\Models\ForumPost;
use App\Modules\Project\Models\Phase;
use App\Modules\Project\Models\Project;
use App\Modules\Tracking\Models\PerformanceSnapshot;
use App\Modules\Tracking\Models\StudentMetric;
use App\Modules\Tracking\Models\StudentProgress;
use Illuminate\Support\Carbon;

/**
 * Corazón del Hito 4. Dos salidas deliberadamente separadas, nunca
 * fusionadas (decisión confirmada):
 *  - progress_pct: mecánico, de evidencias+foro+chat pesados según
 *    TrackingWeightsResolver::forCycle() -- nunca de calidad.
 *  - nivel cualitativo: moda de EvaluationResult del estudiante en TODO el
 *    proyecto, mismo criterio de desempate que Evaluation::consolidatedLevel()
 *    (empate -> nivel más bajo) -- nunca de redondear un promedio.
 *
 * Señales sin "total" natural (foro, chat): sin un conteo "esperado"
 * definido en ningún lado del proyecto, se tratan como binarias -- al menos
 * una participación = señal completa (100% de su peso). Es una decisión de
 * este hito, no una regla ya confirmada; documentado también en el reporte
 * final para que se pueda ajustar si el criterio real es otro (ej. un techo
 * de N participaciones).
 *
 * chat no es atribuible a una fase (chat_messages no tiene project_id ni
 * phase_id, TODO.md #16) -- se cuenta completo en la fila de proyecto
 * (phase_id null) y en 0 en las filas por fase.
 */
final class RecalculateStudentProgressAction
{
    public function __construct(private readonly TrackingWeightsResolver $weightsResolver) {}

    public function execute(User $student, Project $project): void
    {
        $weights = $this->weightsResolver->forCycle($project->cycle);

        $phases = $project->phases()->orderBy('order')->get();

        $projectEvidencesSubmitted = 0;
        $projectEvidencesTotal = 0;
        $projectForumParticipations = 0;
        $lastActiveCandidates = [];

        foreach ($phases as $phase) {
            [$submitted, $total] = $this->evidenceCounts($student, $phase);
            $forumParticipations = $this->forumParticipationsInPhase($student, $phase);

            $phasePct = $this->computeProgressPct($submitted, $total, $forumParticipations, 0, $weights);

            $this->upsertProgress($student, $project, $phase, $phasePct, $submitted, $total, $forumParticipations, 0);

            $projectEvidencesSubmitted += $submitted;
            $projectEvidencesTotal += $total;
            $projectForumParticipations += $forumParticipations;
        }

        $chatParticipations = $this->chatParticipations($student);
        $lastActiveCandidates[] = $this->lastSubmissionAt($student, $project);
        $lastActiveCandidates[] = $this->lastForumPostAt($student, $project);
        $lastActiveCandidates[] = $this->lastChatMessageAt($student);

        $projectPct = $this->computeProgressPct(
            $projectEvidencesSubmitted, $projectEvidencesTotal, $projectForumParticipations, $chatParticipations, $weights,
        );

        $this->upsertProgress(
            $student, $project, null, $projectPct,
            $projectEvidencesSubmitted, $projectEvidencesTotal, $projectForumParticipations, $chatParticipations,
        );

        $lastActiveAt = collect($lastActiveCandidates)->filter()->sort()->last();

        StudentMetric::updateOrCreate(
            ['student_id' => $student->id, 'project_id' => $project->id],
            [
                'progress_pct' => $projectPct,
                'last_active_at' => $lastActiveAt,
                'inactive_days' => $lastActiveAt !== null ? (int) now()->diffInDays($lastActiveAt) : 0,
                // avg_rubric_value, risk_level, risk_score: NUNCA tocados aquí
                // -- ver docblock de StudentMetric.
            ],
        );

        $qualitativeLevel = $this->dominantQualitativeLevel($student, $project);

        PerformanceSnapshot::updateOrCreate(
            ['student_id' => $student->id, 'project_id' => $project->id, 'snapshot_date' => now()->toDateString()],
            ['metrics' => [
                'progress_pct' => $projectPct,
                'qualitative_level_key' => $qualitativeLevel?->key,
            ]],
        );
    }

    /**
     * @return array{0: int, 1: int} [entregadas, esperadas]
     */
    private function evidenceCounts(User $student, Phase $phase): array
    {
        $total = $phase->expectedEvidences()->count();

        $submitted = Submission::query()
            ->where('student_id', $student->id)
            ->whereIn('expected_evidence_id', $phase->expectedEvidences()->pluck('id'))
            ->count();

        return [$submitted, $total];
    }

    private function forumParticipationsInPhase(User $student, Phase $phase): int
    {
        return ForumPost::query()
            ->where('user_id', $student->id)
            ->where('is_hidden', false)
            ->whereHas('thread', fn ($query) => $query->where('phase_id', $phase->id))
            ->count();
    }

    private function chatParticipations(User $student): int
    {
        if ($student->group_id === null) {
            return 0;
        }

        return ChatMessage::query()
            ->where('user_id', $student->id)
            ->where('group_id', $student->group_id)
            ->where('is_hidden', false)
            ->count();
    }

    /**
     * @param  array{evidencias: int, foro: int, chat: int}  $weights
     */
    private function computeProgressPct(
        int $evidencesSubmitted, int $evidencesTotal, int $forumParticipations, int $chatParticipations, array $weights,
    ): int {
        // Sin evidencias esperadas: no hay nada que completar -> señal llena,
        // no penaliza (evita que una fase sin evidencias marque 0%).
        $evidencesRatio = $evidencesTotal > 0
            ? min(1.0, $evidencesSubmitted / $evidencesTotal)
            : 1.0;

        $forumRatio = $forumParticipations > 0 ? 1.0 : 0.0;
        $chatRatio = $chatParticipations > 0 ? 1.0 : 0.0;

        $pct = ($evidencesRatio * $weights['evidencias'])
            + ($forumRatio * $weights['foro'])
            + ($chatRatio * $weights['chat']);

        return (int) round(min(100, max(0, $pct)));
    }

    private function upsertProgress(
        User $student, Project $project, ?Phase $phase, int $pct,
        int $evidencesSubmitted, int $evidencesTotal, int $forumParticipations, int $chatParticipations,
    ): void {
        $existing = StudentProgress::query()
            ->where('student_id', $student->id)
            ->where('project_id', $project->id)
            ->where('phase_id', $phase?->id)
            ->first();

        $attributes = [
            'progress_pct' => $pct,
            'forum_participations' => $forumParticipations,
            'chat_participations' => $chatParticipations,
            'evidences_submitted' => $evidencesSubmitted,
            'evidences_total' => $evidencesTotal,
            'status' => $this->statusFor($pct),
        ];

        if ($existing === null || $existing->started_at === null) {
            if ($pct > 0) {
                $attributes['started_at'] = now();
            }
        }

        if (($existing === null || $existing->completed_at === null) && $pct >= 100) {
            $attributes['completed_at'] = now();
        }

        StudentProgress::updateOrCreate(
            ['student_id' => $student->id, 'project_id' => $project->id, 'phase_id' => $phase?->id],
            $attributes,
        );
    }

    private function statusFor(int $pct): string
    {
        return match (true) {
            $pct >= 100 => 'completed',
            $pct > 0 => 'in_progress',
            default => 'not_started',
        };
    }

    /**
     * Nivel dominante entre TODOS los EvaluationResult del estudiante en el
     * proyecto completo (no solo una evaluación puntual) -- mismo criterio
     * de desempate que Evaluation::consolidatedLevel(): moda, empate gana el
     * nivel más bajo.
     */
    private function dominantQualitativeLevel(User $student, Project $project): ?RubricLevel
    {
        $counts = EvaluationResult::query()
            ->join('evaluations', 'evaluations.id', '=', 'evaluation_results.evaluation_id')
            ->join('submissions', 'submissions.id', '=', 'evaluations.submission_id')
            ->join('expected_evidences', 'expected_evidences.id', '=', 'submissions.expected_evidence_id')
            ->join('phases', 'phases.id', '=', 'expected_evidences.phase_id')
            ->where('submissions.student_id', $student->id)
            ->where('phases.project_id', $project->id)
            ->where('evaluations.evaluator_type', 'teacher')
            ->selectRaw('evaluation_results.rubric_level_id, COUNT(*) as total')
            ->groupBy('evaluation_results.rubric_level_id')
            ->pluck('total', 'evaluation_results.rubric_level_id');

        if ($counts->isEmpty()) {
            return null;
        }

        $maxCount = $counts->max();
        $tiedLevelIds = $counts->filter(fn (int $count): bool => $count === $maxCount)->keys();

        return RubricLevel::whereIn('id', $tiedLevelIds)->orderBy('order')->first();
    }

    private function lastSubmissionAt(User $student, Project $project): ?Carbon
    {
        $max = Submission::query()
            ->where('student_id', $student->id)
            ->whereHas('expectedEvidence.phase', fn ($query) => $query->where('project_id', $project->id))
            ->max('submitted_at');

        return $max !== null ? Carbon::parse($max) : null;
    }

    private function lastForumPostAt(User $student, Project $project): ?Carbon
    {
        $max = ForumPost::query()
            ->where('user_id', $student->id)
            ->whereHas('thread', fn ($query) => $query->where('project_id', $project->id))
            ->max('created_at');

        return $max !== null ? Carbon::parse($max) : null;
    }

    private function lastChatMessageAt(User $student): ?Carbon
    {
        if ($student->group_id === null) {
            return null;
        }

        $max = ChatMessage::query()
            ->where('user_id', $student->id)
            ->where('group_id', $student->group_id)
            ->max('created_at');

        return $max !== null ? Carbon::parse($max) : null;
    }
}
