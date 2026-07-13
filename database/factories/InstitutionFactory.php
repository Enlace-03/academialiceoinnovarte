<?php

namespace Database\Factories;

use App\Modules\Institution\Models\Institution;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Institution>
 */
class InstitutionFactory extends Factory
{
    protected $model = Institution::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'name' => 'Liceo Innovarte',
            'city' => 'Pereira, Colombia',
            'settings' => null,
        ];
    }
}
