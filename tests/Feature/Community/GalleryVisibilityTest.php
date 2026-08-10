<?php

namespace Tests\Feature\Community;

use App\Livewire\Shared\Gallery;
use App\Models\User;
use App\Modules\Community\Models\GalleryPost;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\SchoolGrade;
use App\Modules\Project\Models\Project;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Prueba el listado real del componente Livewire (no solo la Policy) --
 * GalleryPostPolicy::view() y Gallery::posts() deben coincidir siempre, así
 * que aquí se prueba explícitamente el camino que de verdad usa el
 * estudiante/acudiente al entrar a /galeria.
 */
class GalleryVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Project $ownCycleProject;

    private Project $otherCycleProject;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $ownCycle = Cycle::factory()->create();
        $otherCycle = Cycle::factory()->create();
        $ownGrade = SchoolGrade::factory()->create(['cycle_id' => $ownCycle->id]);

        $this->ownCycleProject = Project::factory()->create(['cycle_id' => $ownCycle->id]);
        $this->otherCycleProject = Project::factory()->create(['cycle_id' => $otherCycle->id]);

        $this->student = User::factory()->create(['school_grade_id' => $ownGrade->id])->assignRole('student');
    }

    public function test_student_sees_general_and_own_cycle_posts_but_not_other_cycle(): void
    {
        $general = GalleryPost::factory()->create(['project_id' => null, 'title' => 'General']);
        $ownCycle = GalleryPost::factory()->create(['project_id' => $this->ownCycleProject->id, 'title' => 'Mi ciclo']);
        $otherCycle = GalleryPost::factory()->create(['project_id' => $this->otherCycleProject->id, 'title' => 'Otro ciclo']);

        $ids = Livewire::actingAs($this->student)->test(Gallery::class)
            ->instance()->posts()->pluck('id')->all();

        $this->assertContains($general->id, $ids);
        $this->assertContains($ownCycle->id, $ids);
        $this->assertNotContains($otherCycle->id, $ids);
    }

    public function test_a_post_scheduled_in_the_future_is_not_shown_yet(): void
    {
        $future = GalleryPost::factory()->create([
            'project_id' => null,
            'published_at' => now()->addDay(),
        ]);

        $ids = Livewire::actingAs($this->student)->test(Gallery::class)
            ->instance()->posts()->pluck('id')->all();

        $this->assertNotContains($future->id, $ids);
    }

    public function test_a_non_student_non_parent_cannot_access_the_gallery_page(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');

        Livewire::actingAs($teacher)->test(Gallery::class)->assertForbidden();
    }
}
