<?php

namespace Database\Factories;

use App\Modules\Assessment\Models\Evaluation;
use App\Modules\Assessment\Models\EvaluationResult;
use App\Modules\Assessment\Models\RubricCriterion;
use App\Modules\Assessment\Models\RubricLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluationResult>
 */
class EvaluationResultFactory extends Factory
{
    protected $model = EvaluationResult::class;

    public function definition(): array
    {
        return [
            'evaluation_id' => Evaluation::factory(),
            'rubric_criterion_id' => RubricCriterion::factory(),
            'rubric_level_id' => RubricLevel::factory(),
            'comment' => fake()->optional()->sentence(),
        ];
    }
}
