<?php

namespace Tests\Feature\Student;

use App\Livewire\Student\EvidenceShow;
use App\Models\User;
use App\Modules\Assessment\Models\Evaluation;
use App\Modules\Assessment\Models\EvaluationResult;
use App\Modules\Assessment\Models\Rubric;
use App\Modules\Assessment\Models\RubricCriterion;
use App\Modules\Assessment\Models\RubricLevel;
use App\Modules\Assessment\Models\Submission;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\SchoolGrade;
use App\Modules\Project\Models\ExpectedEvidence;
use App\Modules\Project\Models\Project;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RubricLevelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Hito 3b-3, segunda vuelta: EvidenceShow::resultsByCriterion() alimenta el
 * resaltado por criterio de <x-rubric-criteria-table> (comparación directa
 * contra Google Classroom, que resalta la celda lograda dentro de la propia
 * tabla en vez de solo un badge consolidado aparte). El caso crítico
 * pedido explícitamente: un criterio sin EvaluationResult (sin evaluar, o
 * evaluación parcial) no debe resaltar ninguna celda ni romper la vista.
 */
class EvidenceShowRubricHighlightTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Project $project;

    private ExpectedEvidence $evidence;

    private RubricCriterion $criterionOne;

    private RubricCriterion $criterionTwo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);
        $this->seed(RubricLevelSeeder::class);

        $cycle = Cycle::factory()->create();
        $grade = SchoolGrade::factory()->create(['cycle_id' => $cycle->id]);
        $this->student = User::factory()->create(['school_grade_id' => $grade->id])->assignRole('student');
        $this->project = Project::factory()->create(['cycle_id' => $cycle->id]);

        $phase = $this->project->phases()->first();
        $rubric = Rubric::factory()->create();
        $this->criterionOne = RubricCriterion::factory()->create(['rubric_id' => $rubric->id, 'position' => 1]);
        $this->criterionTwo = RubricCriterion::factory()->create(['rubric_id' => $rubric->id, 'position' => 2]);
        $this->evidence = ExpectedEvidence::factory()->create(['phase_id' => $phase->id, 'rubric_id' => $rubric->id]);
    }

    public function test_returns_empty_array_without_any_evaluation(): void
    {
        Submission::factory()->create([
            'expected_evidence_id' => $this->evidence->id,
            'student_id' => $this->student->id,
        ]);

        $this->actingAs($this->student);
        $component = Livewire::test(EvidenceShow::class, ['project' => $this->project, 'evidence' => $this->evidence]);

        $this->assertSame([], $component->instance()->resultsByCriterion());
    }

    /**
     * El caso pedido explícitamente: evaluación parcial (un criterio con
     * EvaluationResult, el otro no). El criterio sin resultado no debe
     * aparecer en el array -- el componente Blade lo resuelve con `?? null`
     * y no resalta nada para ese criterio, sin error.
     */
    public function test_partial_evaluation_only_includes_the_evaluated_criterion(): void
    {
        $submission = Submission::factory()->create([
            'expected_evidence_id' => $this->evidence->id,
            'student_id' => $this->student->id,
        ]);
        $evaluation = Evaluation::factory()->create([
            'submission_id' => $submission->id,
            'evaluator_type' => 'teacher',
        ]);
        $level = RubricLevel::where('key', 'logro_esperado')->firstOrFail();
        EvaluationResult::factory()->create([
            'evaluation_id' => $evaluation->id,
            'rubric_criterion_id' => $this->criterionOne->id,
            'rubric_level_id' => $level->id,
        ]);

        $this->actingAs($this->student);
        $component = Livewire::test(EvidenceShow::class, ['project' => $this->project, 'evidence' => $this->evidence]);

        $results = $component->instance()->resultsByCriterion();

        $this->assertCount(1, $results);
        $this->assertArrayHasKey($this->criterionOne->id, $results);
        $this->assertArrayNotHasKey($this->criterionTwo->id, $results);
        $this->assertTrue($results[$this->criterionOne->id]->is($level));

        // La vista renderiza sin error y muestra "Sin evaluar" para el
        // criterio sin resultado, en vez de romper o resaltar algo falso.
        $component->assertSee('Sin evaluar');
        $component->assertSee($level->label);
    }

    public function test_full_evaluation_maps_every_criterion_to_its_achieved_level(): void
    {
        $submission = Submission::factory()->create([
            'expected_evidence_id' => $this->evidence->id,
            'student_id' => $this->student->id,
        ]);
        $evaluation = Evaluation::factory()->create([
            'submission_id' => $submission->id,
            'evaluator_type' => 'teacher',
        ]);
        $levelOne = RubricLevel::where('key', 'inicio')->firstOrFail();
        $levelTwo = RubricLevel::where('key', 'logro_destacado')->firstOrFail();
        EvaluationResult::factory()->create([
            'evaluation_id' => $evaluation->id,
            'rubric_criterion_id' => $this->criterionOne->id,
            'rubric_level_id' => $levelOne->id,
        ]);
        EvaluationResult::factory()->create([
            'evaluation_id' => $evaluation->id,
            'rubric_criterion_id' => $this->criterionTwo->id,
            'rubric_level_id' => $levelTwo->id,
        ]);

        $this->actingAs($this->student);
        $component = Livewire::test(EvidenceShow::class, ['project' => $this->project, 'evidence' => $this->evidence]);

        $results = $component->instance()->resultsByCriterion();

        $this->assertCount(2, $results);
        $this->assertTrue($results[$this->criterionOne->id]->is($levelOne));
        $this->assertTrue($results[$this->criterionTwo->id]->is($levelTwo));
        $component->assertDontSee('Sin evaluar');
    }
}
