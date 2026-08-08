<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Community\Models\ChatMessage;
use App\Modules\Institution\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    protected $model = ChatMessage::class;

    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'user_id' => User::factory(),
            'content' => fake()->sentence(),
        ];
    }
}
