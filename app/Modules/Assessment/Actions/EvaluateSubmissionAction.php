<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Actions;

use App\Models\User;
use App\Modules\Assessment\Events\SubmissionEvaluated;
use App\Modules\Assessment\Models\Evaluation;
use App\Modules\Assessment\Models\EvaluationResult;
use App\Modules\Assessment\Models\Submission;
use Illuminate\Support\Facades\DB;

/**
 * unique(submission_id, evaluator_type) permite reevaluar (actualiza la
 * misma Evaluation de ese tipo) sin crear duplicados, y deja espacio para
 * auto/coevaluación futura sin volver a migrar — hoy solo se usa 'teacher'.
 */
final class EvaluateSubmissionAction
{
    /**
     * @param  array<int, int>  $criteriaResults  ['rubric_criterion_id' => 'rubric_level_id']
     */
    public function execute(
        User $evaluator,
        Submission $submission,
        array $criteriaResults,
        ?string $feedback = null,
        string $evaluatorType = 'teacher',
    ): Evaluation {
        return DB::transaction(function () use ($evaluator, $submission, $criteriaResults, $feedback, $evaluatorType) {
            $evaluation = Evaluation::updateOrCreate(
                [
                    'submission_id' => $submission->id,
                    'evaluator_type' => $evaluatorType,
                ],
                [
                    'evaluated_by' => $evaluator->id,
                    'feedback' => $feedback,
                    'evaluated_at' => now(),
                ],
            );

            foreach ($criteriaResults as $criterionId => $rubricLevelId) {
                EvaluationResult::updateOrCreate(
                    [
                        'evaluation_id' => $evaluation->id,
                        'rubric_criterion_id' => $criterionId,
                    ],
                    ['rubric_level_id' => $rubricLevelId],
                );
            }

            $submission->update(['status' => 'evaluated']);

            event(new SubmissionEvaluated($evaluation));

            return $evaluation;
        });
    }
}
