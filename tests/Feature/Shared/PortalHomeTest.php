<?php

namespace Tests\Feature\Shared;

use App\Livewire\Shared\PortalHome;
use App\Models\User;
use App\Modules\Assessment\Models\Submission;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\Institution;
use App\Modules\Institution\Models\SchoolGrade;
use App\Modules\Institution\Models\ThinkingField;
use App\Modules\Project\Models\ExpectedEvidence;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\StudentPhaseSchedule;
use App\Modules\Tracking\Models\StudentProgress;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PortalHomeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Institution::factory()->create();
    }

    public function test_guardian_sees_pending_evidence_for_their_children(): void
    {
        $guardian = User::factory()->create()->assignRole('parent');
        $child = User::factory()->create(['name' => 'Estudiante Ejemplo'])->assignRole('student');
        $guardian->children()->attach($child->id, ['relationship' => 'padre']);

        $project = Project::factory()->create();
        $phase = $project->phases()->first();
        $evidence = ExpectedEvidence::factory()->create(['phase_id' => $phase->id, 'description' => 'Ensayo final']);
        StudentPhaseSchedule::factory()->create([
            'student_id' => $child->id,
            'phase_id' => $phase->id,
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        Livewire::actingAs($guardian)->test(PortalHome::class)
            ->assertSee('Estudiante Ejemplo')
            ->assertSee('Ensayo final');
    }

    public function test_guardian_does_not_see_evidence_already_submitted(): void
    {
        $guardian = User::factory()->create()->assignRole('parent');
        $child = User::factory()->create()->assignRole('student');
        $guardian->children()->attach($child->id, ['relationship' => 'padre']);

        $project = Project::factory()->create();
        $phase = $project->phases()->first();
        $evidence = ExpectedEvidence::factory()->create(['phase_id' => $phase->id, 'description' => 'Evidencia ya entregada']);
        StudentPhaseSchedule::factory()->create([
            'student_id' => $child->id,
            'phase_id' => $phase->id,
            'end_date' => now()->addDays(5)->toDateString(),
        ]);
        Submission::factory()->create([
            'expected_evidence_id' => $evidence->id,
            'student_id' => $child->id,
            'status' => 'submitted',
        ]);

        Livewire::actingAs($guardian)->test(PortalHome::class)
            ->assertDontSee('Evidencia ya entregada');
    }

    /**
     * Hito 4b: primera pieza de progreso real del dashboard del acudiente
     * -- una por hijo, mismo cálculo que el portal de estudiante (ya
     * cubierto a fondo en AggregateThinkingFieldProgressActionTest).
     */
    public function test_guardian_sees_thinking_field_progress_for_each_child(): void
    {
        $guardian = User::factory()->create()->assignRole('parent');

        // order=3 (Contextual, ciclo tardío) explícito -- este test verifica
        // la barra numérica de siempre; el reemplazo por estrellas en
        // ciclos 1-2 tiene su propia cobertura dedicada (ver Hito de
        // estrellas). Sin fijarlo, Cycle::factory() cae en un order
        // aleatorio 1-4 y este test queda intermitente.
        $cycle = Cycle::factory()->create(['order' => 3]);
        $grade = SchoolGrade::factory()->create(['cycle_id' => $cycle->id]);
        $child = User::factory()->create(['name' => 'Estudiante Ejemplo', 'school_grade_id' => $grade->id])->assignRole('student');
        $guardian->children()->attach($child->id, ['relationship' => 'padre']);

        $field = ThinkingField::factory()->create(['name' => 'Campo de prueba']);
        $project = Project::factory()->create(['cycle_id' => $cycle->id]);
        $project->thinkingFields()->attach($field->id);
        StudentProgress::factory()->create([
            'student_id' => $child->id,
            'project_id' => $project->id,
            'phase_id' => null,
            'progress_pct' => 65,
        ]);

        Livewire::actingAs($guardian)->test(PortalHome::class)
            ->assertSee('Avance por campo de pensamiento')
            ->assertSee('Campo de prueba')
            ->assertSee('65%');
    }

    /**
     * Hito de estrellas: un hijo de ciclo 1-2 (Exploratorio, Conceptual) se
     * ve con <x-progress-stars>, nunca la barra ni el "{{ pct }}%" al mismo
     * tiempo -- User::isInEarlyCycle() evaluado sobre el HIJO, no sobre el
     * acudiente.
     */
    public function test_guardian_sees_stars_instead_of_the_bar_for_a_child_in_an_early_cycle(): void
    {
        $guardian = User::factory()->create()->assignRole('parent');

        $earlyCycle = Cycle::factory()->create(['order' => 2]);
        $grade = SchoolGrade::factory()->create(['cycle_id' => $earlyCycle->id]);
        $child = User::factory()->create(['school_grade_id' => $grade->id])->assignRole('student');
        $guardian->children()->attach($child->id, ['relationship' => 'padre']);

        $field = ThinkingField::factory()->create(['name' => 'Campo temprano']);
        $project = Project::factory()->create(['cycle_id' => $earlyCycle->id]);
        $project->thinkingFields()->attach($field->id);
        StudentProgress::factory()->create([
            'student_id' => $child->id,
            'project_id' => $project->id,
            'phase_id' => null,
            'progress_pct' => 47,
        ]);

        Livewire::actingAs($guardian)->test(PortalHome::class)
            ->assertSee('aria-label="47% de avance"', false)
            ->assertDontSee('<span class="text-gray-500">47%</span>', false)
            ->assertDontSee('bg-emerald-500 h-2.5 rounded-full', false);
    }

    /**
     * El caso que motivó este redirect: personal que entra por /login
     * compartido (o cae acá por un bookmark viejo, o sesión ya abierta) no
     * tiene nada útil en este placeholder -- se manda directo al panel.
     */
    public function test_staff_visiting_home_is_redirected_to_academia(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');

        $this->actingAs($teacher)
            ->get(route('portal.home'))
            ->assertRedirect(Filament::getPanel('academic')->getUrl());
    }

    public function test_student_visiting_home_sees_portal_home_normally(): void
    {
        $student = User::factory()->create()->assignRole('student');

        $this->actingAs($student)
            ->get(route('portal.home'))
            ->assertOk()
            ->assertSee('Ver mis proyectos');
    }

    public function test_parent_visiting_home_sees_portal_home_normally(): void
    {
        $parent = User::factory()->create()->assignRole('parent');

        $this->actingAs($parent)
            ->get(route('portal.home'))
            ->assertOk();
    }

    /**
     * Sin riesgo de bucle (caso de borde improbable, pero vale decirlo
     * explícito): isStaff() y canAccessPanel('academic') son hoy la misma
     * condición, así que este escenario no puede darse en el código actual
     * -- pero si algún día divergieran, esto confirma que el panel académico
     * responde con 403 cuando canAccessPanel() es false, NUNCA con un
     * redirect de vuelta a '/'. Un usuario sin rol staff (ej. un estudiante,
     * que definitivamente no pasa canAccessPanel('academic')) golpeando la
     * raíz del panel es la forma más directa de probar esa respuesta real,
     * sin necesidad de mockear ni forzar artificialmente una divergencia
     * entre los dos chequeos.
     */
    public function test_academic_panel_root_denies_non_staff_with_403_not_a_redirect(): void
    {
        $student = User::factory()->create()->assignRole('student');

        $response = $this->actingAs($student)->get(Filament::getPanel('academic')->getUrl());

        $response->assertForbidden();
        $response->assertHeaderMissing('Location');
    }
}
