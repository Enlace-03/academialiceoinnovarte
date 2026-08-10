<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Modules\Community\Models\GalleryPost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GalleryPost>
 */
class GalleryPostFactory extends Factory
{
    protected $model = GalleryPost::class;

    public function definition(): array
    {
        return [
            'project_id' => null,
            'created_by_user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'caption' => fake()->optional()->paragraph(),
            'published_at' => now(),
        ];
    }
}
