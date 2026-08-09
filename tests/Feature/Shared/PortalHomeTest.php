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
}
