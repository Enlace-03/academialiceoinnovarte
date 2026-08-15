<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use App\Modules\Identity\Actions\BlockStudentPhotoUploadsAction;
use App\Modules\Identity\Actions\RemoveStudentPhotoAction;
use App\Modules\Identity\Actions\UnblockStudentPhotoUploadsAction;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Lado personal de la foto de perfil de estudiante: students.photo.moderate
 * (decisión confirmada: solo coordinator y rector, nunca teacher ni
 * secretary) -- ver config/permissions.php. Autoridad real, a nivel de
 * Action -- la cobertura de la UI de /academia que consume estas mismas
 * Actions vive en un commit aparte, junto con el Resource que la expone.
 */
class StudentPhotoModerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        Storage::fake('local');
    }

    private function studentWithPhoto(): User
    {
        $student = User::factory()->create()->assignRole('student');
        $path = "student-photos/{$student->uuid}.jpg";
        Storage::disk('local')->put($path, 'contenido-de-prueba');
        $student->forceFill(['photo_disk' => 'local', 'photo_path' => $path])->save();

        return $student->fresh();
    }

    public function test_coordinator_can_remove_a_students_photo_and_it_is_logged(): void
    {
        $coordinator = User::factory()->create()->assignRole('coordinator');
        $student = $this->studentWithPhoto();
        $path = $student->photo_path;

        app(RemoveStudentPhotoAction::class)->execute($coordinator, $student);

        $student->refresh();
        $this->assertFalse($student->hasPhoto());
        Storage::disk('local')->assertMissing($path);

        $this->assertDatabaseHas('student_photo_moderation_log', [
            'student_id' => $student->id,
            'action' => 'removed',
            'performed_by_user_id' => $coordinator->id,
        ]);
    }

    public function test_rector_can_block_and_unblock_uploads_and_blocking_removes_the_current_photo(): void
    {
        $rector = User::factory()->create()->assignRole('rector');
        $student = $this->studentWithPhoto();
        $path = $student->photo_path;

        app(BlockStudentPhotoUploadsAction::class)->execute($rector, $student);

        $student->refresh();
        $this->assertTrue($student->photo_upload_blocked);
        $this->assertFalse($student->hasPhoto());
        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseHas('student_photo_moderation_log', [
            'student_id' => $student->id,
            'action' => 'blocked',
            'performed_by_user_id' => $rector->id,
        ]);

        app(UnblockStudentPhotoUploadsAction::class)->execute($rector, $student);

        $student->refresh();
        $this->assertFalse($student->photo_upload_blocked);
        $this->assertDatabaseHas('student_photo_moderation_log', [
            'student_id' => $student->id,
            'action' => 'unblocked',
            'performed_by_user_id' => $rector->id,
        ]);
    }

    public function test_teacher_cannot_remove_a_students_photo(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $student = $this->studentWithPhoto();

        $this->expectException(AuthorizationException::class);

        app(RemoveStudentPhotoAction::class)->execute($teacher, $student);
    }

    public function test_secretary_cannot_block_uploads(): void
    {
        $secretary = User::factory()->create()->assignRole('secretary');
        $student = $this->studentWithPhoto();

        $this->expectException(AuthorizationException::class);

        app(BlockStudentPhotoUploadsAction::class)->execute($secretary, $student);
    }

    public function test_teacher_and_secretary_cannot_unblock_either(): void
    {
        $student = $this->studentWithPhoto();
        $student->forceFill(['photo_upload_blocked' => true])->save();

        foreach (['teacher', 'secretary'] as $role) {
            $staff = User::factory()->create()->assignRole($role);
            $rejected = false;

            try {
                app(UnblockStudentPhotoUploadsAction::class)->execute($staff, $student);
            } catch (AuthorizationException) {
                $rejected = true;
            }

            $this->assertTrue($rejected, "Se esperaba que {$role} no pudiera desbloquear.");
        }
    }
}
