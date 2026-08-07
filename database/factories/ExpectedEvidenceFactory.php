<?php

namespace Database\Factories;

use App\Modules\Project\Models\ExpectedEvidence;
use App\Modules\Project\Models\Phase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpectedEvidence>
 */
class ExpectedEvidenceFactory extends Factory
{
    protected $model = ExpectedEvidence::class;

    public function definition(): array
    {
        return [
            'phase_id' => Phase::factory(),
            'type' => fake()->randomElement(array_keys(ExpectedEvidence::TYPES)),
            'description' => fake()->sentence(),
            'is_required' => true,
            'alternative_group' => null,
        ];
    }
}
