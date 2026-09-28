<?php

namespace Tests\Feature\Shared;

use App\Models\User;
use App\Modules\Assessment\Models\SubmissionAttachment;
use App\Modules\Community\Models\ForumPostPhoto;
use App\Modules\Community\Models\GalleryPhoto;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * App\Http\Middleware\SandboxPrivateFileResponse en las cuatro rutas que
 * sirven archivos subidos por usuarios desde el disco privado. Se consulta
 * como super_admin (Gate::before) para aislar la cabecera de la
 * autorización de cada ruta, que ya tiene sus propios tests.
 */
class PrivateFileSandboxHeaderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);

        Storage::fake('local');

        $this->actingAs(User::factory()->create()->assignRole('super_admin'));
    }

    public function test_gallery_photos_are_served_sandboxed(): void
    {
        $photo = GalleryPhoto::factory()->create();

        $this->assertSandboxed($this->get(route('gallery.photos.show', $photo)));
    }

    public function test_forum_photos_are_served_sandboxed(): void
    {
        $photo = ForumPostPhoto::factory()->create();

        $this->assertSandboxed($this->get(route('forum.photos.show', $photo)));
    }

    public function test_submission_attachments_are_served_sandboxed(): void
    {
        $attachment = SubmissionAttachment::factory()->photo()->create();

        $this->assertSandboxed($this->get(route('submissions.attachments.show', $attachment)));
    }

    public function test_student_profile_photos_are_served_sandboxed(): void
    {
        Storage::disk('local')->put('student-photos/foto.jpg', 'contenido');
        $student = User::factory()->create([
            'photo_disk' => 'local',
            'photo_path' => 'student-photos/foto.jpg',
        ])->assignRole('student');

        $this->assertSandboxed($this->get(route('students.photo.show', $student)));
    }

    private function assertSandboxed(TestResponse $response): void
    {
        $response->assertOk();
        $response->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");
    }
}
