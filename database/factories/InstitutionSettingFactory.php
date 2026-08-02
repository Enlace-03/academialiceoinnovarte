<?php

namespace Database\Factories;

use App\Modules\Institution\Models\Institution;
use App\Modules\Institution\Models\InstitutionSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstitutionSetting>
 */
class InstitutionSettingFactory extends Factory
{
    protected $model = InstitutionSetting::class;

    public function definition(): array
    {
        return [
            'institution_id' => Institution::factory(),
            'key' => fake()->unique()->word(),
            'value' => fake()->word(),
        ];
    }
}
