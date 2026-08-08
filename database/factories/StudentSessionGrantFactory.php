<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Identity\Models\StudentSessionGrant;
use App\Modules\Institution\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentSessionGrant>
 */
class StudentSessionGrantFactory extends Factory
{
    protected $model = StudentSessionGrant::class;

    public function definition(): array
    {
        return [
            'student_id' => User::factory(),
            'granted_by_user_id' => User::factory(),
            'group_id' => Group::factory(),
            'started_at' => now(),
            'ended_at' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
