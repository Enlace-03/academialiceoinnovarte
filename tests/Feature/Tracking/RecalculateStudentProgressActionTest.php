<?php

namespace Tests\Feature\Tracking;

use App\Models\User;
use App\Modules\Assessment\Models\Evaluation;
use App\Modules\Assessment\Models\EvaluationResult;
use App\Modules\Assessment\Models\RubricLevel;
use App\Modules\Assessment\Models\Submission;
use App\Modules\Community\Models\ChatMessage;
use App\Modules\Community\Models\ForumPost;
use App\Modules\Community\Models\ForumThread;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\Group;
use App\Modules\Institution\Models\InstitutionSetting;
use App\Modules\Institution\Models\Institution;
use App\Modules\Project\Models\ExpectedEvidence;
use App\Modules\Project\Models\Project;
use App\Modules\Tracking\Actions\RecalculateStudentProgressAction;
use App\Modules\Tracking\Actions\TrackingWeightsResolver;
use App\Modules\Tracking\Models\PerformanceSnapshot;
use App\Modules\Tracking\Models\StudentMetric;
use App\Modules\Tracking\Models\StudentProgress;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RubricLevelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecalculateStudentProgressActionTest extends TestCase
{
    use RefreshDatabase;

    private Cycle $cycle;

    private Group $group;

    private User $student;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);
        $this->seed(RubricLevelSeeder::class);
        Institution::factory()->create();

        $this->cycle = Cycle::factory()->create();
        $this->group = Group::factory()->create(['cycle_id' => $this->cycle->id]);
        $this->student = User::factory()->create(['group_id' => $this->group->id])->assignRole('student');
        $this->project = Project::factory()->create(['cycle_id' => $this->cycle->id]);
    }

    public function test_progress_pct_uses_default_weights_when_fully_covered(): void
    {
        $phase = $this->project->phases()->first();
        $evidence = ExpectedEvidence::factory()->create(['phase_id' => $phase->id]);
        Submission::factory()->create(['expected_evidence_id' => $evidence->id, 'student_id' => $this->student->id]);

        $thread = ForumThread::factory()->create(['project_id' => $this->project->id, 'phase_id' => $phase->id]);
        ForumPost::factory()->create(['forum_thread_id' => $thread->id, 'user_id' => $this->student->id]);

        ChatMessage::factory()->create(['group_id' => $this->group->id, 'user_id' => $this->student->id]);

        app(RecalculateStudentProgressAction::class)->execute($this->student, $this->project);

        $progress = StudentProgress::where('student_id', $this->student->id)
            ->where('project_id', $this->project->id)->whereNull('phase_id')->first();

        // 100% evidencias (60) + participó en foro (25) + participó en chat (15) = 100
        $this->assertSame(100, $progress->progress_pct);
        $this->assertSame('completed', $progress->status);
        $this->assertNotNull($progress->completed_at);
    }

    public function test_progress_pct_is_partial_when_only_evidences_are_covered(): void
    {
        $phase = $this->project->phases()->first();
        $evidence = ExpectedEvidence::factory()->create(['phase_id' => $phase->id]);
        Submission::factory()->create(['expected_evidence_id' => $evidence->id, 'student_id' => $this->student->id]);

        app(RecalculateStudentProgressAction::class)->execute($this->student, $this->project);

        $progress = StudentProgress::where('student_id', $this->student->id)
            ->where('project_id', $this->project->id)->whereNull('phase_id')->first();

        $this->assertSame(60, $progress->progress_pct);
        $this->assertSame('in_progress', $progress->status);
        $this->assertNull($progress->completed_at);
    }

    public function test_progress_pct_respects_cycle_specific_weight_override(): void
    {
        InstitutionSetting::set(TrackingWeightsResolver::cycleKey($this->cycle->id), json_encode([
            'evidencias' => 20, 'foro' => 20, 'chat' => 60,
        ]));

        // Evidencia esperada SIN entregar: aísla la señal de chat. Una fase
        // sin ninguna evidencia esperada da crédito completo automático a
        // ese componente (ver computeProgressPct) -- sin esto, evidencias
        // contribuiría su peso completo igual, contaminando la aserción.
        $phase = $this->project->phases()->first();
        ExpectedEvidence::factory()->create(['phase_id' => $phase->id]);

        ChatMessage::factory()->create(['group_id' => $this->group->id, 'user_id' => $this->student->id]);

        app(RecalculateStudentProgressAction::class)->execute($this->student, $this->project);

        $progress = StudentProgress::where('student_id', $this->student->id)
            ->where('project_id', $this->project->id)->whereNull('phase_id')->first();

        $this->assertSame(60, $progress->progress_pct);
    }

    public function test_a_project_of_another_cycle_is_unaffected_by_this_cycles_override(): void
    {
        InstitutionSetting::set(TrackingWeightsResolver::cycleKey($this->cycle->id), json_encode([
            'evidencias' => 0, 'foro' => 0, 'chat' => 100,
        ]));

        $otherCycle = Cycle::factory()->create();
        $otherGroup = Group::factory()->create(['cycle_id' => $otherCycle->id]);
        $otherStudent = User::factory()->create(['group_id' => $otherGroup->id])->assignRole('student');
        $otherProject = Project::factory()->create(['cycle_id' => $otherCycle->id]);

        // Mismo motivo que el test anterior: aísla la señal de chat.
        $otherPhase = $otherProject->phases()->first();
        ExpectedEvidence::factory()->create(['phase_id' => $otherPhase->id]);

        ChatMessage::factory()->create(['group_id' => $otherGroup->id, 'user_id' => $otherStudent->id]);

        app(RecalculateStudentProgressAction::class)->execute($otherStudent, $otherProject);

        $progress = StudentProgress::where('student_id', $otherStudent->id)
            ->where('project_id', $otherProject->id)->whereNull('phase_id')->first();

        // Debe usar el default (60/25/15), no el override del otro ciclo:
        // chat solo aporta 15, no 100.
        $this->assertSame(15, $progress->progress_pct);
    }

    public function test_qualitative_level_is_the_mode_with_lowest_level_winning_ties(): void
    {
        $phase = $this->project->phases()->first();
        $teacher = User::factory()->create()->assignRole('teacher');
        $evidenceA = ExpectedEvidence::factory()->create(['phase_id' => $phase->id]);
        $evidenceB = ExpectedEvidence::factory()->create(['phase_id' => $phase->id]);

        $submissionA = Submission::factory()->create(['expected_evidence_id' => $evidenceA->id, 'student_id' => $this->student->id]);
        $submissionB = Submission::factory()->create(['expected_evidence_id' => $evidenceB->id, 'student_id' => $this->student->id]);

        $enProceso = RubricLevel::where('key', 'en_proceso')->firstOrFail();
        $logroDestacado = RubricLevel::where('key', 'logro_destacado')->firstOrFail();

        // Empate 1-1 entre en_proceso y logro_destacado -> gana el más bajo (en_proceso).
        $evalA = Evaluation::factory()->create(['submission_id' => $submissionA->id, 'evaluator_type' => 'teacher', 'evaluated_by' => $teacher->id]);
        EvaluationResult::factory()->create(['evaluation_id' => $evalA->id, 'rubric_level_id' => $enProceso->id]);

        $evalB = Evaluation::factory()->create(['submission_id' => $submissionB->id, 'evaluator_type' => 'teacher', 'evaluated_by' => $teacher->id]);
        EvaluationResult::factory()->create(['evaluation_id' => $evalB->id, 'rubric_level_id' => $logroDestacado->id]);

        app(RecalculateStudentProgressAction::class)->execute($this->student, $this->project);

        $snapshot = PerformanceSnapshot::where('student_id', $this->student->id)
            ->where('project_id', $this->project->id)->first();

        $this->assertSame('en_proceso', $snapshot->metrics['qualitative_level_key']);
    }

    public function test_risk_level_and_risk_score_are_never_touched(): void
    {
        StudentMetric::factory()->create([
            'student_id' => $this->student->id,
            'project_id' => $this->project->id,
            'risk_level' => 'high',
            'risk_score' => 87.5,
        ]);

        app(RecalculateStudentProgressAction::class)->execute($this->student, $this->project);

        $metric = StudentMetric::where('student_id', $this->student->id)->where('project_id', $this->project->id)->first();

        $this->assertSame('high', $metric->risk_level);
        $this->assertEquals(87.5, $metric->risk_score);
    }

    public function test_avg_rubric_value_stays_null_never_a_numeric_proxy_for_quality(): void
    {
        app(RecalculateStudentProgressAction::class)->execute($this->student, $this->project);

        $metric = StudentMetric::where('student_id', $this->student->id)->where('project_id', $this->project->id)->first();

        $this->assertNull($metric->avg_rubric_value);
    }

    public function test_guides_columns_stay_at_zero_no_tracked_signal_today(): void
    {
        app(RecalculateStudentProgressAction::class)->execute($this->student, $this->project);

        $progress = StudentProgress::where('student_id', $this->student->id)
            ->where('project_id', $this->project->id)->whereNull('phase_id')->first();

        $this->assertSame(0, $progress->guides_completed);
        $this->assertSame(0, $progress->guides_total);
    }

    public function test_performance_snapshot_upserts_the_same_row_for_the_same_day_no_duplicates(): void
    {
        app(RecalculateStudentProgressAction::class)->execute($this->student, $this->project);
        app(RecalculateStudentProgressAction::class)->execute($this->student, $this->project);
        app(RecalculateStudentProgressAction::class)->execute($this->student, $this->project);

        $this->assertSame(1, PerformanceSnapshot::where('student_id', $this->student->id)
            ->where('project_id', $this->project->id)->count());
    }

    public function test_chat_participation_counts_at_project_level_but_not_attributed_to_a_phase(): void
    {
        ChatMessage::factory()->create(['group_id' => $this->group->id, 'user_id' => $this->student->id]);

        app(RecalculateStudentProgressAction::class)->execute($this->student, $this->project);

        $phase = $this->project->phases()->first();

        $phaseRow = StudentProgress::where('student_id', $this->student->id)
            ->where('project_id', $this->project->id)->where('phase_id', $phase->id)->first();
        $projectRow = StudentProgress::where('student_id', $this->student->id)
            ->where('project_id', $this->project->id)->whereNull('phase_id')->first();

        $this->assertSame(0, $phaseRow->chat_participations);
        $this->assertSame(1, $projectRow->chat_participations);
    }
}
