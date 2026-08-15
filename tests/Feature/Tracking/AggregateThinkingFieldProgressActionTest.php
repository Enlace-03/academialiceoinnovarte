<?php

namespace Tests\Feature\Tracking;

use App\Models\User;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\SchoolGrade;
use App\Modules\Institution\Models\ThinkingField;
use App\Modules\Project\Models\Project;
use App\Modules\Tracking\Actions\AggregateThinkingFieldProgressAction;
use App\Modules\Tracking\Models\StudentProgress;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AggregateThinkingFieldProgressActionTest extends TestCase
{
    use RefreshDatabase;

    private Cycle $cycle;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);

        $this->cycle = Cycle::factory()->create();
        $grade = SchoolGrade::factory()->create(['cycle_id' => $this->cycle->id]);
        $this->student = User::factory()->create(['school_grade_id' => $grade->id])->assignRole('student');
    }

    private function projectTouchingField(ThinkingField $field, ?int $progressPct, string $status = 'published'): Project
    {
        $project = Project::factory()->create(['cycle_id' => $this->cycle->id, 'status' => $status]);
        $project->thinkingFields()->attach($field->id);

        if ($progressPct !== null) {
            StudentProgress::factory()->create([
                'student_id' => $this->student->id,
                'project_id' => $project->id,
                'phase_id' => null,
                'progress_pct' => $progressPct,
            ]);
        }

        return $project;
    }

    public function test_averages_progress_across_multiple_projects_touching_the_same_field(): void
    {
        $field = ThinkingField::factory()->create();
        $this->projectTouchingField($field, 40);
        $this->projectTouchingField($field, 80);

        $result = app(AggregateThinkingFieldProgressAction::class)->execute($this->student);
        $entry = $result->firstWhere('thinkingField.id', $field->id);

        $this->assertNotNull($entry);
        $this->assertSame(60, $entry['progressPct']);
    }

    public function test_a_field_with_no_projects_touching_it_is_omitted_not_zero(): void
    {
        $fieldWithProjects = ThinkingField::factory()->create(['order' => 1]);
        $fieldWithoutProjects = ThinkingField::factory()->create(['order' => 2]);
        $this->projectTouchingField($fieldWithProjects, 50);

        $result = app(AggregateThinkingFieldProgressAction::class)->execute($this->student);

        $this->assertCount(1, $result);
        $this->assertNull($result->firstWhere('thinkingField.id', $fieldWithoutProjects->id));
    }

    /**
     * Caso real dado el hito de borrador/publicado recién cerrado: un
     * proyecto draft que toca el campo no debe entrar al promedio en
     * absoluto -- si contara, (80+0)/2 daría 40; el resultado correcto es
     * 80, como si el draft no existiera.
     */
    public function test_a_draft_project_does_not_count_toward_the_average(): void
    {
        $field = ThinkingField::factory()->create();
        $this->projectTouchingField($field, 80, 'published');
        $this->projectTouchingField($field, 0, 'draft');

        $result = app(AggregateThinkingFieldProgressAction::class)->execute($this->student);
        $entry = $result->firstWhere('thinkingField.id', $field->id);

        $this->assertSame(80, $entry['progressPct']);
    }

    /**
     * Un proyecto published que toca el campo pero para el que el
     * estudiante todavía no tiene fila en student_progress (cero
     * submissions/foro/chat) cuenta como 0% -- distinto del caso "campo sin
     * ningún proyecto", que se omite.
     */
    public function test_a_touching_project_without_a_progress_row_counts_as_zero(): void
    {
        $field = ThinkingField::factory()->create();
        $this->projectTouchingField($field, 100);
        $this->projectTouchingField($field, null);

        $result = app(AggregateThinkingFieldProgressAction::class)->execute($this->student);
        $entry = $result->firstWhere('thinkingField.id', $field->id);

        $this->assertSame(50, $entry['progressPct']);
    }

    public function test_student_without_school_grade_returns_empty(): void
    {
        $studentWithoutGrade = User::factory()->create(['school_grade_id' => null])->assignRole('student');

        $result = app(AggregateThinkingFieldProgressAction::class)->execute($studentWithoutGrade);

        $this->assertTrue($result->isEmpty());
    }
}
