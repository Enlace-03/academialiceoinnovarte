<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Community\Models\ForumPost;
use App\Modules\Community\Models\ForumPostPhoto;
use Database\Factories\Concerns\CreatesFakeImageFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForumPostPhoto>
 */
class ForumPostPhotoFactory extends Factory
{
    use CreatesFakeImageFile;

    protected $model = ForumPostPhoto::class;

    public function definition(): array
    {
        return [
            'forum_post_id' => ForumPost::factory(),
            'file_disk' => 'local',
            'file_path' => $this->putFakeImage('local', 'forum-photos'),
            'original_filename' => fake()->word().'.jpg',
            'order' => 0,
        ];
    }
}
