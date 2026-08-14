<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Assessment\Models\Submission;
use App\Modules\Assessment\Models\SubmissionAttachment;
use Database\Factories\Concerns\CreatesFakeImageFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubmissionAttachment>
 */
class SubmissionAttachmentFactory extends Factory
{
    use CreatesFakeImageFile;

    protected $model = SubmissionAttachment::class;

    public function definition(): array
    {
        return [
            'submission_id' => Submission::factory(),
            'type' => 'photo',
            'file_disk' => 'local',
            'file_path' => $this->putFakeImage('local', 'submissions'),
            'original_filename' => fake()->word().'.jpg',
            'url' => null,
            'is_youtube' => false,
            'order' => 0,
        ];
    }

    public function photo(): static
    {
        return $this->state(fn (): array => [
            'type' => 'photo',
            'file_disk' => 'local',
            'file_path' => $this->putFakeImage('local', 'submissions'),
            'original_filename' => fake()->word().'.jpg',
            'url' => null,
            'is_youtube' => false,
        ]);
    }

    public function link(bool $isYoutube = false): static
    {
        return $this->state(fn (): array => [
            'type' => 'link',
            'file_disk' => null,
            'file_path' => null,
            'original_filename' => null,
            'url' => $isYoutube
                ? 'https://www.youtube.com/watch?v='.fake()->regexify('[A-Za-z0-9_-]{11}')
                : fake()->url(),
            'is_youtube' => $isYoutube,
        ]);
    }
}
