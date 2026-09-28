<?php

namespace Tests\Feature\Assessment;

use App\Models\User;
use App\Modules\Assessment\Models\ReportCard;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Ruta report-cards.show: UUID, ReportCardPolicy::view() contra el
 * estudiante, descarga con Content-Disposition: attachment y CSP sandbox.
 */
class ReportCardRouteTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private ReportCard $reportCard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);

        Storage::fake('local');

        $this->student = User::factory()->create()->assignRole('student');
        $this->reportCard = ReportCard::factory()->total()->create([
            'student_id' => $this->student->id,
            'academic_year' => 2027,
        ]);
    }

    private function url(): string
    {
        return route('report-cards.show', $this->reportCard);
    }

    public function test_the_url_uses_the_uuid_and_not_the_numeric_id(): void
    {
        $this->assertStringContainsString($this->reportCard->uuid, $this->url());
        $this->assertStringNotContainsString('/boletines/'.$this->reportCard->id, $this->url());
        $this->actingAs(User::factory()->create()->assignRole('rector'))
            ->get('/boletines/'.$this->reportCard->id)
            ->assertNotFound();
    }

    public function test_the_guardian_downloads_it_as_an_attachment_with_the_sandbox_csp(): void
    {
        $guardian = User::factory()->create()->assignRole('parent');
        $guardian->children()->attach($this->student->id, ['relationship' => 'madre']);

        $response = $this->actingAs($guardian)->get($this->url());

        $response->assertOk();
        $disposition = $response->headers->get('content-disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('boletin-total-2027.pdf', $disposition);
        $response->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");
    }

    public function test_rector_coordinator_and_teacher_can_download_it(): void
    {
        foreach (['rector', 'coordinator', 'teacher'] as $role) {
            $this->actingAs(User::factory()->create()->assignRole($role))
                ->get($this->url())
                ->assertOk();
        }
    }

    public function test_secretary_the_student_and_other_users_are_forbidden(): void
    {
        $otherStudent = User::factory()->create()->assignRole('student');
        $otherGuardian = User::factory()->create()->assignRole('parent');
        $otherGuardian->children()->attach($otherStudent->id, ['relationship' => 'madre']);

        $forbidden = [
            User::factory()->create()->assignRole('secretary'),
            $this->student,
            $otherStudent,
            $otherGuardian,
        ];

        foreach ($forbidden as $user) {
            $this->actingAs($user)->get($this->url())->assertForbidden();
        }
    }

    public function test_a_guest_is_redirected(): void
    {
        $this->get($this->url())->assertRedirect();
    }
}
