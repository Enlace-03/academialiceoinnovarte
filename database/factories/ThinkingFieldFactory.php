<?php

namespace Database\Factories;

use App\Modules\Institution\Models\Institution;
use App\Modules\Institution\Models\ThinkingField;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ThinkingField>
 */
class ThinkingFieldFactory extends Factory
{
    protected $model = ThinkingField::class;

    public function definition(): array
    {
        $order = fake()->unique()->numberBetween(1, 4);
        $name = "Campo de Pensamiento {$order}";

        return [
            'institution_id' => Institution::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'purpose' => fake()->sentence(),
            'order' => $order,
        ];
    }
}
