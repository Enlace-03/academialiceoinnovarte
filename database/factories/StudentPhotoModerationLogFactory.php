<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Identity\Models\StudentPhotoModerationLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentPhotoModerationLog>
 */
class StudentPhotoModerationLogFactory extends Factory
{
    protected $model = StudentPhotoModerationLog::class;

    public function definition(): array
    {
        return [
            'student_id' => User::factory(),
            'action' => fake()->randomElement(array_keys(StudentPhotoModerationLog::ACTIONS)),
            'performed_by_user_id' => User::factory(),
        ];
    }
}
