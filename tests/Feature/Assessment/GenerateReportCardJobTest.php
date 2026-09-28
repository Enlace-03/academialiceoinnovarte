<?php

namespace Tests\Feature\Assessment;

use App\Models\User;
use App\Modules\Assessment\Actions\GenerateReportCardAction;
use App\Modules\Assessment\Events\ReportCardGenerated;
use App\Modules\Assessment\Exceptions\ReportCardAlreadyExistsException;
use App\Modules\Assessment\Jobs\GenerateReportCardJob;
use App\Modules\Assessment\Models\Evaluation;
use App\Modules\Assessment\Models\EvaluationResult;
use App\Modules\Assessment\Models\ReportCard;
use App\Modules\Assessment\Models\RubricCriterion;
use App\Modules\Assessment\Models\RubricLevel;
use App\Modules\Assessment\Models\Submission;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\SchoolGrade;
use App\Modules\Institution\Models\ThinkingField;
use App\Modules\Project\Models\ExpectedEvidence;
use App\Modules\Project\Models\Project;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RubricLevelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * GenerateReportCardJob + GenerateReportCardAction. QUEUE_CONNECTION=sync en
 * tests: dispatchSync ejecuta el job en el mismo proceso.
 */
class GenerateReportCardJobTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private SchoolGrade $grade;

    private Cycle $cycle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);
        $this->seed(RubricLevelSeeder::class);

        Storage::fake('local');
        config()->set('school.current_academic_year', 2027);

        $this->cycle = Cycle::factory()->create();
        $this->grade = SchoolGrade::factory()->create(['cycle_id' => $this->cycle->id]);
        $this->student = User::factory()->create(['school_grade_id' => $this->grade->id, 'name' => 'Ana Prueba'])->assignRole('student');
    }

    /**
     * @param  list<string>  $levelKeys  un EvaluationResult por cada key
     */
    private function evaluate(Project $project, array $levelKeys, string $evaluatorType = 'teacher'): void
    {
        $evidence = ExpectedEvidence::factory()->create(['phase_id' => $project->phases()->first()->id]);
        $submission = Submission::factory()->create(['expected_evidence_id' => $evidence->id, 'student_id' => $this->student->id]);
        $evaluation = Evaluation::factory()->create(['submission_id' => $submission->id, 'evaluator_type' => $evaluatorType]);

        foreach ($levelKeys as $key) {
            EvaluationResult::create([
                'evaluation_id' => $evaluation->id,
                'rubric_criterion_id' => RubricCriterion::factory()->create()->id,
                'rubric_level_id' => RubricLevel::where('key', $key)->firstOrFail()->id,
            ]);
        }
    }

    private function project(int $year = 2027, string $title = 'Proyecto A', array $fields = []): Project
    {
        $project = Project::factory()->create(['cycle_id' => $this->cycle->id, 'year' => $year, 'title' => $title]);

        foreach ($fields as $field) {
            $project->thinkingFields()->attach($field->id);
        }

        return $project;
    }

    public function test_a_parcial_report_card_is_generated_as_a_real_pdf_on_the_local_disk(): void
    {
        $generator = User::factory()->create()->assignRole('teacher');

        GenerateReportCardJob::dispatchSync($this->student->id, 'parcial', false, $generator->id);

        $reportCard = ReportCard::firstOrFail();

        $this->assertSame($this->student->id, $reportCard->student_id);
        $this->assertSame($this->grade->id, $reportCard->school_grade_id);
        $this->assertSame(2027, $reportCard->academic_year);
        $this->assertSame('parcial', $reportCard->type);
        $this->assertSame('local', $reportCard->file_disk);
        $this->assertSame($generator->id, $reportCard->generated_by);
        Storage::disk('local')->assertExists($reportCard->file_path);
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($reportCard->file_path));
    }

    public function test_parcial_always_generates_a_new_one_without_restriction(): void
    {
        GenerateReportCardJob::dispatchSync($this->student->id, 'parcial');
        GenerateReportCardJob::dispatchSync($this->student->id, 'parcial');

        $this->assertSame(2, ReportCard::where('type', 'parcial')->count());
        $this->assertCount(2, Storage::disk('local')->allFiles('report-cards'));
    }

    public function test_total_fails_if_one_already_exists_for_the_same_student_grade_and_year(): void
    {
        GenerateReportCardJob::dispatchSync($this->student->id, 'total');
        $first = ReportCard::firstOrFail();

        try {
            GenerateReportCardJob::dispatchSync($this->student->id, 'total');
            $this->fail('El segundo boletín total debió fallar.');
        } catch (ReportCardAlreadyExistsException) {
            // esperado
        }

        $this->assertSame(1, ReportCard::count());
        $this->assertSame($first->file_path, $first->fresh()->file_path);
        $this->assertCount(1, Storage::disk('local')->allFiles('report-cards'));
    }

    public function test_total_with_regenerate_replaces_the_row_and_deletes_the_old_file(): void
    {
        GenerateReportCardJob::dispatchSync($this->student->id, 'total');
        $first = ReportCard::firstOrFail();
        $oldPath = $first->file_path;

        GenerateReportCardJob::dispatchSync($this->student->id, 'total', true);

        $this->assertSame(1, ReportCard::count());
        $reportCard = $first->fresh();
        $this->assertNotSame($oldPath, $reportCard->file_path);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($reportCard->file_path);
        $this->assertCount(1, Storage::disk('local')->allFiles('report-cards'));
    }

    public function test_a_total_for_a_different_year_or_grade_is_not_a_duplicate(): void
    {
        GenerateReportCardJob::dispatchSync($this->student->id, 'total');

        config()->set('school.current_academic_year', 2028);
        GenerateReportCardJob::dispatchSync($this->student->id, 'total');

        $this->assertSame(2, ReportCard::where('type', 'total')->count());
    }

    public function test_the_report_data_converts_levels_with_the_scale_and_uses_the_lowest_level_on_ties(): void
    {
        $analitico = ThinkingField::factory()->create(['name' => 'Pensamiento analítico']);
        $creativo = ThinkingField::factory()->create(['name' => 'Pensamiento creativo']);

        // Proyecto A (analítico + creativo): 2x logro_destacado + 1x inicio -> moda logro_destacado (5).
        $this->evaluate($this->project(2027, 'Proyecto A', [$analitico, $creativo]), ['logro_destacado', 'logro_destacado', 'inicio']);
        // Proyecto B (solo analítico): 1x logro_esperado + 1x en_proceso -> empate, gana el más bajo: en_proceso (3).
        $this->evaluate($this->project(2027, 'Proyecto B', [$analitico]), ['logro_esperado', 'en_proceso']);

        $data = app(GenerateReportCardAction::class)->reportData($this->student, 2027);

        $projects = collect($data['projects'])->keyBy('title');
        $this->assertSame(5, $projects['Proyecto A']['number']);
        $this->assertSame('Superior', $projects['Proyecto A']['performance']);
        $this->assertSame(3, $projects['Proyecto B']['number']);
        $this->assertSame('Básico', $projects['Proyecto B']['performance']);

        // Campo analítico: niveles de A y B juntos = 2 destacado, 1 inicio, 1 esperado, 1 en_proceso -> moda destacado (5).
        // Campo creativo: solo A -> destacado (5).
        $fields = collect($data['fields'])->keyBy('name');
        $this->assertSame(5, $fields['Pensamiento analítico']['number']);
        $this->assertSame('Logro destacado', $fields['Pensamiento analítico']['level']);
        $this->assertSame(5, $fields['Pensamiento creativo']['number']);
    }

    public function test_only_teacher_evaluations_of_the_academic_year_count_and_none_becomes_a_one(): void
    {
        $field = ThinkingField::factory()->create();

        $this->evaluate($this->project(2026, 'Del año pasado', [$field]), ['logro_destacado']);
        $this->evaluate($this->project(2027, 'Autoevaluado', [$field]), ['logro_destacado'], 'self');

        $data = app(GenerateReportCardAction::class)->reportData($this->student, 2027);

        $this->assertSame([], $data['projects']);
        $this->assertSame([], $data['fields']);
    }

    public function test_no_evaluated_level_is_ever_converted_to_the_absent_value(): void
    {
        $field = ThinkingField::factory()->create();
        $this->evaluate($this->project(2027, 'Inicio', [$field]), ['inicio']);

        $data = app(GenerateReportCardAction::class)->reportData($this->student, 2027);

        $this->assertSame(2, $data['projects'][0]['number']);
        $this->assertNotSame(1, $data['projects'][0]['number']);
    }

    public function test_the_pdf_view_has_no_remote_resources_and_no_flex_or_grid(): void
    {
        $html = view('pdf.report-card', [
            'institution' => 'Liceo Innovarte',
            'studentName' => 'Ana Prueba',
            'gradeName' => '3°',
            'academicYear' => 2027,
            'title' => 'Boletín parcial',
            'generatedAt' => '01/01/2027',
            'fields' => [['name' => 'Campo', 'level' => 'Logro esperado', 'number' => 4, 'performance' => 'Alto']],
            'projects' => [],
        ])->render();

        $this->assertDoesNotMatchRegularExpression('#(src|href)\s*=\s*["\']?https?://#i', $html);
        $this->assertDoesNotMatchRegularExpression('#url\(\s*["\']?https?://#i', $html);
        $this->assertStringNotContainsString('display: flex', $html);
        $this->assertStringNotContainsString('display: grid', $html);
        $this->assertStringContainsString('<table>', $html);
    }

    public function test_generating_fires_the_domain_event(): void
    {
        Event::fake([ReportCardGenerated::class]);

        GenerateReportCardJob::dispatchSync($this->student->id, 'parcial');

        Event::assertDispatched(ReportCardGenerated::class);
    }

    public function test_an_invalid_type_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new GenerateReportCardJob($this->student->id, 'semestral');
    }

    public function test_a_student_without_a_grade_fails_and_leaves_no_row_or_file(): void
    {
        $noGrade = User::factory()->create(['school_grade_id' => null])->assignRole('student');

        try {
            GenerateReportCardJob::dispatchSync($noGrade->id, 'parcial');
            $this->fail('Debió fallar sin grado.');
        } catch (\RuntimeException) {
            // esperado
        }

        $this->assertSame(0, ReportCard::count());
        $this->assertSame([], Storage::disk('local')->allFiles('report-cards'));
    }
}
