<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Community\Models\PrivateChatMessage;
use App\Modules\Community\Models\PrivateChatThread;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrivateChatMessage>
 */
class PrivateChatMessageFactory extends Factory
{
    protected $model = PrivateChatMessage::class;

    public function definition(): array
    {
        return [
            'thread_id' => PrivateChatThread::factory(),
            'user_id' => User::factory(),
            'content' => fake()->sentence(),
        ];
    }
}
