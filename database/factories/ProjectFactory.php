<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Project\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * status='published' por defecto (a diferencia del default 'draft' de la
 * columna real): un dato de prueba "usable" por defecto -- la mayoría de la
 * suite existente crea proyectos esperando que sean visibles para
 * estudiantes, sin relación con el hito de borrador/publicado. Usar
 * ->draft() explícitamente en los tests que sí necesitan un borrador.
 *
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'cycle_id' => Cycle::factory(),
            'created_by_user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'status' => 'published',
            'problem_situation' => fake()->paragraphs(2, true),
            'guiding_question' => fake()->sentence().'?',
            'purpose' => fake()->paragraph(),
            'semester' => fake()->randomElement([1, 2]),
            'year' => (int) config('school.current_academic_year'),
            'suggested_duration_weeks' => fake()->numberBetween(4, 16),
            'expected_impact' => fake()->paragraph(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => 'draft']);
    }
}
