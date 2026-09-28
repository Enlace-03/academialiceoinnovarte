<?php

namespace Database\Factories;

use App\Modules\Assessment\Models\Evaluation;
use App\Modules\Assessment\Models\EvaluationAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @extends Factory<EvaluationAttachment>
 */
class EvaluationAttachmentFactory extends Factory
{
    protected $model = EvaluationAttachment::class;

    public function definition(): array
    {
        $path = 'evaluation-feedback/'.Str::random(20).'.pdf';

        // Los tests deben llamar Storage::fake('local') en su setUp().
        Storage::disk('local')->put($path, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");

        return [
            'evaluation_id' => Evaluation::factory(),
            'file_disk' => 'local',
            'file_path' => $path,
            'original_filename' => fake()->word().'.pdf',
        ];
    }
}
