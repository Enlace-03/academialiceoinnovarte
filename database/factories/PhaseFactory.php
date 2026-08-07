<?php

namespace Database\Factories;

use App\Modules\Project\Models\Phase;
use App\Modules\Project\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Phase>
 */
class PhaseFactory extends Factory
{
    protected $model = Phase::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->sentence(3),
            // Todo Project ya nace con sus 4 fases institucionales (order 1-4)
            // vía ProjectObserver. Esta factory es para fases de prueba
            // adicionales, así que usa un order fuera de ese rango para no
            // chocar con la unique(project_id, order). order es tinyint
            // unsigned (máx 255) en la migración.
            'order' => fake()->unique()->numberBetween(5, 250),
            'description' => fake()->paragraph(),
        ];
    }
}
