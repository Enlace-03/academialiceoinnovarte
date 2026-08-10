<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Community\Models\GalleryPhoto;
use App\Modules\Community\Models\GalleryPost;
use Database\Factories\Concerns\CreatesFakeImageFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GalleryPhoto>
 */
class GalleryPhotoFactory extends Factory
{
    use CreatesFakeImageFile;

    protected $model = GalleryPhoto::class;

    public function definition(): array
    {
        return [
            'gallery_post_id' => GalleryPost::factory(),
            'file_disk' => 'local',
            'file_path' => $this->putFakeImage('local', 'gallery-photos'),
            'original_filename' => fake()->word().'.jpg',
            'order' => 0,
        ];
    }
}
