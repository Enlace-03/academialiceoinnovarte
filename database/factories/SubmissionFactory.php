<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Assessment\Models\Submission;
use App\Modules\Project\Models\ExpectedEvidence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Submission>
 */
class SubmissionFactory extends Factory
{
    protected $model = Submission::class;

    public function definition(): array
    {
        return [
            'expected_evidence_id' => ExpectedEvidence::factory(),
            'student_id' => User::factory(),
            'text_content' => fake()->paragraph(),
            'status' => 'submitted',
            'submitted_at' => now(),
        ];
    }
}
