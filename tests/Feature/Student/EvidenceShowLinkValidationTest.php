<?php

namespace Tests\Feature\Student;

use App\Livewire\Student\EvidenceShow;
use App\Models\User;
use App\Modules\Assessment\Models\Submission;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\SchoolGrade;
use App\Modules\Project\Models\ExpectedEvidence;
use App\Modules\Project\Models\Project;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 'url' de Laravel, sin restricción de esquema, deja pasar 'javascript:' y
 * otros esquemas no-web -- un enlace guardado así se renderiza tal cual en
 * <a href> (ver x-youtube-embed) y se ejecutaría al hacer clic. 'url:http,
 * https' restringe a los dos únicos esquemas que tienen sentido para un
 * enlace de evidencia.
 */
class EvidenceShowLinkValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Project $project;

    private ExpectedEvidence $evidence;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);

        $cycle = Cycle::factory()->create();
        $grade = SchoolGrade::factory()->create(['cycle_id' => $cycle->id]);
        $this->student = User::factory()->create(['school_grade_id' => $grade->id])->assignRole('student');
        $this->project = Project::factory()->create(['cycle_id' => $cycle->id]);
        $this->evidence = ExpectedEvidence::factory()->create(['phase_id' => $this->project->phases()->first()->id]);

        $this->actingAs($this->student);
    }

    public function test_add_link_rejects_a_javascript_scheme(): void
    {
        Livewire::test(EvidenceShow::class, ['project' => $this->project, 'evidence' => $this->evidence])
            ->set('linkInput', 'javascript://x%0Aalert(1)')
            ->call('addLink')
            ->assertHasErrors('linkInput');
    }

    public function test_add_link_rejects_an_ftp_scheme(): void
    {
        Livewire::test(EvidenceShow::class, ['project' => $this->project, 'evidence' => $this->evidence])
            ->set('linkInput', 'ftp://x.co')
            ->call('addLink')
            ->assertHasErrors('linkInput');
    }

    public function test_add_link_accepts_an_https_url(): void
    {
        Livewire::test(EvidenceShow::class, ['project' => $this->project, 'evidence' => $this->evidence])
            ->set('linkInput', 'https://example.com/recurso')
            ->call('addLink')
            ->assertHasNoErrors('linkInput');
    }

    /**
     * addLink() ya filtra en la entrada, pero submit() es la frontera real
     * (newLinks es una public property de Livewire, input de cliente no
     * confiable por sí solo) -- se fuerza el array directamente para probar
     * esa segunda capa sin pasar por addLink().
     */
    public function test_submit_rejects_a_non_http_scheme_forced_directly_into_new_links(): void
    {
        Livewire::test(EvidenceShow::class, ['project' => $this->project, 'evidence' => $this->evidence])
            ->set('newLinks', [['url' => 'javascript://x%0Aalert(1)', 'is_youtube' => false]])
            ->call('submit')
            ->assertHasErrors('newLinks.0.url');

        $this->assertSame(0, Submission::count());
    }

    public function test_submit_accepts_an_https_link(): void
    {
        Livewire::test(EvidenceShow::class, ['project' => $this->project, 'evidence' => $this->evidence])
            ->set('newLinks', [['url' => 'https://example.com/recurso', 'is_youtube' => false]])
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertSame(1, Submission::where('student_id', $this->student->id)->count());
    }
}
