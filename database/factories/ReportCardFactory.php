<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Modules\Assessment\Models\ReportCard;
use App\Modules\Institution\Models\SchoolGrade;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReportCard>
 */
class ReportCardFactory extends Factory
{
    protected $model = ReportCard::class;

    public function definition(): array
    {
        $path = 'report-cards/'.Str::random(20).'.pdf';

        // Los tests deben llamar Storage::fake('local') en su setUp().
        Storage::disk('local')->put($path, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");

        return [
            'student_id' => User::factory(),
            'school_grade_id' => SchoolGrade::factory(),
            'academic_year' => 2027,
            'type' => ReportCard::TYPE_PARCIAL,
            'file_disk' => 'local',
            'file_path' => $path,
            'generated_by' => null,
            'generated_at' => now(),
        ];
    }

    public function total(): static
    {
        return $this->state(['type' => ReportCard::TYPE_TOTAL]);
    }
}
