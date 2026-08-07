<?php

namespace Database\Factories;

use App\Modules\Project\Models\Phase;
use App\Modules\Project\Models\Resource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Resource>
 */
class ResourceFactory extends Factory
{
    protected $model = Resource::class;

    public function definition(): array
    {
        return [
            'phase_id' => Phase::factory(),
            'guide_id' => null,
            'title' => fake()->sentence(3),
            'type' => fake()->randomElement(array_keys(Resource::TYPES)),
            'url_or_path' => fake()->url(),
        ];
    }
}
