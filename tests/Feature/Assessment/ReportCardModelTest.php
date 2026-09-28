<?php

namespace Tests\Feature\Assessment;

use App\Models\User;
use App\Modules\Assessment\Models\ReportCard;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportCardModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_the_table_has_no_period_column(): void
    {
        $this->assertTrue(Schema::hasColumns('report_cards', [
            'uuid', 'student_id', 'school_grade_id', 'academic_year', 'type',
            'file_disk', 'file_path', 'generated_by', 'generated_at',
        ]));
        $this->assertFalse(Schema::hasColumn('report_cards', 'period_id'));
    }

    public function test_a_report_card_gets_a_uuid_and_its_relations(): void
    {
        $generator = User::factory()->create();
        $reportCard = ReportCard::factory()->total()->create(['generated_by' => $generator->id]);

        $this->assertNotEmpty($reportCard->uuid);
        $this->assertSame('total', $reportCard->type);
        $this->assertTrue($reportCard->generatedBy->is($generator));
        $this->assertNotNull($reportCard->student);
        $this->assertNotNull($reportCard->schoolGrade);
    }

    public function test_deleting_a_report_card_removes_its_file_from_disk(): void
    {
        $reportCard = ReportCard::factory()->create();
        Storage::disk('local')->assertExists($reportCard->file_path);

        $reportCard->delete();

        Storage::disk('local')->assertMissing($reportCard->file_path);
    }

    public function test_the_database_restricts_deleting_a_student_who_has_a_report_card(): void
    {
        $reportCard = ReportCard::factory()->create();

        $this->expectException(QueryException::class);

        $reportCard->student->delete();
    }
}
