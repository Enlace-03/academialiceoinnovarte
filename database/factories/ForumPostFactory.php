<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Community\Models\ForumPost;
use App\Modules\Community\Models\ForumThread;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForumPost>
 */
class ForumPostFactory extends Factory
{
    protected $model = ForumPost::class;

    public function definition(): array
    {
        return [
            'forum_thread_id' => ForumThread::factory(),
            'parent_post_id' => null,
            'user_id' => User::factory(),
            'content' => fake()->paragraph(),
        ];
    }
}
