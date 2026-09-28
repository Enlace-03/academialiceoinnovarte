<?php

namespace Tests\Feature\Shared;

use App\Filament\Academic\Resources\GalleryPosts\Pages\CreateGalleryPost;
use App\Filament\Academic\Resources\Projects\Pages\EditProject;
use App\Filament\Academic\Resources\Projects\RelationManagers\ExpectedEvidencesRelationManager;
use App\Models\User;
use App\Modules\Assessment\Models\Submission;
use App\Modules\Community\Models\GalleryPost;
use App\Modules\Project\Models\ExpectedEvidence;
use App\Modules\Project\Models\Project;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Los FileUpload de personal (galería y registro de entrega por el
 * docente) usaban ->image(), que acepta 'image/*' y por tanto SVG -- un
 * formato que puede llevar scripts. Ahora aceptan solo jpeg/png/webp/gif,
 * lo mismo que la regla 'image' de Laravel en el lado Livewire.
 *
 * El SVG de prueba tiene contenido real: la regla mimetypes adivina el tipo
 * por el contenido del archivo, no por el MIME que declara el cliente. Cada
 * caso trae su control positivo (un PNG real pasa) para que el rechazo no
 * sea por un formulario mal armado.
 */
class StaffUploadsRejectSvgTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    /** Repeater::fake() cambia estado estático -- se deshace en tearDown(). */
    private \Closure $undoRepeaterFake;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);

        Storage::fake('local');

        // Claves de ítem predecibles (0, 1, ...) en vez de UUIDs, para poder
        // afirmar el error en 'photos.0.file_path' / 'attachments.0.file_path'.
        $this->undoRepeaterFake = Repeater::fake();

        $this->teacher = User::factory()->create()->assignRole('teacher');
        $this->actingAs($this->teacher);
        Filament::setCurrentPanel(Filament::getPanel('academic'));
    }

    protected function tearDown(): void
    {
        ($this->undoRepeaterFake)();

        parent::tearDown();
    }

    public function test_the_gallery_form_rejects_an_svg(): void
    {
        Livewire::test(CreateGalleryPost::class)
            ->fillForm($this->galleryData($this->svg()))
            ->call('create')
            ->assertHasFormErrors(['photos.0.file_path']);

        $this->assertSame(0, GalleryPost::count());
    }

    public function test_the_gallery_form_accepts_a_png(): void
    {
        Livewire::test(CreateGalleryPost::class)
            ->fillForm($this->galleryData(UploadedFile::fake()->image('foto.png')))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, GalleryPost::count());
    }

    public function test_registering_a_submission_as_teacher_rejects_an_svg(): void
    {
        [$manager, $evidence, $student] = $this->mountRegisterSubmission();

        $manager->setTableActionData($this->submissionData($student, $this->svg()))
            ->callMountedTableAction()
            ->assertHasTableActionErrors(['attachments.0.file_path']);

        $this->assertSame(0, Submission::where('expected_evidence_id', $evidence->id)->count());
    }

    public function test_registering_a_submission_as_teacher_accepts_a_png(): void
    {
        [$manager, $evidence, $student] = $this->mountRegisterSubmission();

        $manager->setTableActionData($this->submissionData($student, UploadedFile::fake()->image('foto.png')))
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame(1, Submission::where('expected_evidence_id', $evidence->id)->count());
    }

    private function galleryData(UploadedFile $file): array
    {
        return [
            'title' => 'Salida pedagógica',
            'published_at' => now(),
            'photos' => [['file_disk' => 'local', 'file_path' => $file]],
        ];
    }

    private function submissionData(User $student, UploadedFile $file): array
    {
        return [
            'student_id' => $student->id,
            'attachments' => [['type' => 'photo', 'file_path' => $file]],
        ];
    }

    private function mountRegisterSubmission(): array
    {
        $student = User::factory()->create()->assignRole('student');
        $project = Project::factory()->create(['created_by_user_id' => $this->teacher->id]);
        $evidence = ExpectedEvidence::factory()->for($project->phases()->first())->create();

        $manager = Livewire::test(ExpectedEvidencesRelationManager::class, [
            'ownerRecord' => $project,
            'pageClass' => EditProject::class,
        ])->mountTableAction('registerSubmission', $evidence);

        return [$manager, $evidence, $student];
    }

    private function svg(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'foto.svg',
            '<?xml version="1.0" encoding="UTF-8"?>'
            .'<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10">'
            .'<script>alert(document.cookie)</script><rect width="10" height="10"/></svg>',
        );
    }
}
