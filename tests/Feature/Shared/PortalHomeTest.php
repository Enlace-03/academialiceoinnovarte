<?php

namespace Tests\Feature\Shared;

use App\Livewire\Shared\PortalHome;
use App\Models\User;
use App\Modules\Assessment\Models\Submission;
use App\Modules\Institution\Models\Institution;
use App\Modules\Project\Models\ExpectedEvidence;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\StudentPhaseSchedule;
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
