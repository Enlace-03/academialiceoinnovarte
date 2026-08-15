<?php

namespace Tests\Feature\Project;

use App\Filament\Academic\Resources\Projects\Pages\EditProject;
use App\Filament\Academic\Resources\Projects\RelationManagers\StudentProgressRelationManager;
use App\Models\User;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\SchoolGrade;
use App\Modules\Institution\Models\ThinkingField;
use App\Modules\Project\Models\Project;
use App\Modules\Tracking\Models\StudentProgress;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StudentProgressRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * "Ver progreso por campo" (Hito 4b): confirma que el modal muestra el
     * agregado REAL entre proyectos -- (40+80)/2 = 60% -- no solo el 40% de
     * la fila puntual sobre la que se hizo clic. Es la prueba de que
     * reutiliza AggregateThinkingFieldProgressAction (cross-proyecto) en vez
     * de mostrar el dato de un solo StudentProgress.
     */
    public function test_thinking_field_progress_action_shows_the_aggregate_across_projects(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);

        $teacher = User::factory()->create()->assignRole('teacher');
        $this->actingAs($teacher);
        Filament::setCurrentPanel(Filament::getPanel('academic'));

        $cycle = Cycle::factory()->create();
        $grade = SchoolGrade::factory()->create(['cycle_id' => $cycle->id]);
        $student = User::factory()->create(['school_grade_id' => $grade->id])->assignRole('student');

        $field = ThinkingField::factory()->create();

        $project1 = Project::factory()->create(['cycle_id' => $cycle->id, 'created_by_user_id' => $teacher->id]);
        $project1->thinkingFields()->attach($field->id);
        $progressRow = StudentProgress::factory()->create([
            'student_id' => $student->id,
            'project_id' => $project1->id,
            'phase_id' => null,
            'progress_pct' => 40,
        ]);

        $project2 = Project::factory()->create(['cycle_id' => $cycle->id]);
        $project2->thinkingFields()->attach($field->id);
        StudentProgress::factory()->create([
            'student_id' => $student->id,
            'project_id' => $project2->id,
            'phase_id' => null,
            'progress_pct' => 80,
        ]);

        $manager = Livewire::test(StudentProgressRelationManager::class, [
            'ownerRecord' => $project1,
            'pageClass' => EditProject::class,
        ]);

        $manager->mountTableAction('thinkingFieldProgress', $progressRow);

        // El contenido del modal no queda en el html() de la snapshot inicial
        // (Filament 4 lo renderiza vía un partial aparte) -- se toma la
        // Action ya montada y se resuelve su modalContent() directamente,
        // sin depender de ese detalle de renderizado para la aserción.
        $modalContent = (string) $manager->instance()->getMountedAction()->getModalContent();

        $this->assertStringContainsString($field->name, $modalContent);
        $this->assertStringContainsString('60%', $modalContent);
    }
}
