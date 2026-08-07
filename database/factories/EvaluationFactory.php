<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Assessment\Models\Evaluation;
use App\Modules\Assessment\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Evaluation>
 */
class EvaluationFactory extends Factory
{
    protected $model = Evaluation::class;

    public function definition(): array
    {
        return [
            'submission_id' => Submission::factory(),
            'evaluated_by' => User::factory(),
            'evaluator_type' => 'teacher',
            'feedback' => fake()->sentence(),
            'evaluated_at' => now(),
        ];
    }
}
