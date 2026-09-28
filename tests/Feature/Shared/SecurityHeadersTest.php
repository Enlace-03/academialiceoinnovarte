<?php

namespace Tests\Feature\Shared;

use App\Models\User;
use App\Modules\Assessment\Models\Submission;
use App\Modules\Assessment\Models\SubmissionAttachment;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * App\Http\Middleware\SecurityHeaders, registrado como middleware global en
 * bootstrap/app.php.
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);
    }

    public function test_a_normal_page_response_carries_the_security_headers(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $this->assertSecurityHeaders($response);
    }

    public function test_a_filament_panel_response_carries_the_security_headers(): void
    {
        $response = $this->get('/admin/login');

        $response->assertOk();
        $this->assertSecurityHeaders($response);
    }

    public function test_a_private_submission_attachment_response_carries_the_security_headers(): void
    {
        Storage::fake('local');

        $student = User::factory()->create()->assignRole('student');
        $submission = Submission::factory()->create(['student_id' => $student->id]);
        $attachment = SubmissionAttachment::factory()->photo()->create(['submission_id' => $submission->id]);

        $response = $this->actingAs($student)->get(route('submissions.attachments.show', $attachment));

        $response->assertOk();
        $this->assertSecurityHeaders($response);
    }

    public function test_hsts_is_not_sent_outside_production(): void
    {
        $this->get(route('login'))->assertHeaderMissing('Strict-Transport-Security');
    }

    private function assertSecurityHeaders(TestResponse $response): void
    {
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}
