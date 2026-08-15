<?php

namespace Tests\Feature\Identity;

use App\Filament\Academic\Resources\Students\Pages\ListStudents;
use App\Models\User;
use App\Modules\Identity\Actions\BlockStudentPhotoUploadsAction;
use App\Modules\Identity\Actions\RemoveStudentPhotoAction;
use App\Modules\Identity\Actions\UnblockStudentPhotoUploadsAction;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Lado personal de la foto de perfil de estudiante: students.photo.moderate
 * (decisión confirmada: solo coordinator y rector, nunca teacher ni
 * secretary) -- ver config/permissions.php. Verificado en dos capas, mismo
 * criterio de defensa en profundidad que el resto del proyecto: la Action
 * (autoridad real) y la tabla de /academia (lo que el usuario efectivamente
 * ve -- students.photo.moderate es un permiso atómico aparte de users.*
 * / institution.*, así que esta UI vive en /academia, NUNCA en /admin, para no
 * romper la separación de paneles ya fijada como decisión de arquitectura).
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

    public function test_coordinator_sees_photo_moderation_actions_in_academic_students_resource(): void
    {
        $coordinator = User::factory()->create()->assignRole('coordinator');
        $this->actingAs($coordinator);
        Filament::setCurrentPanel(Filament::getPanel('academic'));

        $student = $this->studentWithPhoto();

        Livewire::test(ListStudents::class)
            ->assertTableActionVisible('removeStudentPhoto', $student)
            ->assertTableActionVisible('blockStudentPhotoUploads', $student)
            ->assertTableActionHidden('unblockStudentPhotoUploads', $student);
    }

    /**
     * A diferencia de /admin (donde secretary sí entraba a UsersTable pero
     * sin ver las acciones), acá la Resource entera está gateada por
     * students.photo.moderate vía canViewAny() -- secretary no llega ni al
     * listado, 403 real, no una lista con botones ocultos.
     */
    public function test_secretary_cannot_access_the_academic_students_resource_at_all(): void
    {
        $secretary = User::factory()->create()->assignRole('secretary');
        $this->studentWithPhoto();

        $this->actingAs($secretary)
            ->get(route('filament.academic.resources.students.index'))
            ->assertForbidden();
    }

    /**
     * El caso concreto que motivó el punto 1 de la revisión de arquitectura:
     * students.photo.moderate es un permiso atómico aparte de users.*
     * / institution.* -- alguien que lo tenga y NADA más debe poder ver y usar
     * esta pantalla igual, porque vive en /academia (acceso por ser personal,
     * isStaff()) y no en /admin (acceso por prefijo de permiso). No alcanza
     * con inspeccionar el código: se verifica de punta a punta, incluyendo
     * que ese mismo usuario NO puede entrar a /admin en absoluto.
     */
    public function test_a_user_with_only_the_atomic_permission_reaches_academia_but_never_admin(): void
    {
        // Se despoja al rol coordinator (ya sembrado con su preset completo
        // en setUp) de todo menos el permiso bajo prueba -- aislado a esta
        // transacción de test, no afecta a otros. givePermissionTo/
        // syncPermissions a nivel de USUARIO no alcanzaría: el rol seguiría
        // aportando users.view/users.create por su cuenta (confirmado antes
        // de escribir este test).
        \Spatie\Permission\Models\Role::findByName('coordinator')->syncPermissions(['students.photo.moderate']);

        $moderator = User::factory()->create()->assignRole('coordinator');
        $this->actingAs($moderator);

        $this->assertFalse($moderator->canAccessPanel(Filament::getPanel('admin')));
        $this->assertTrue($moderator->canAccessPanel(Filament::getPanel('academic')));

        Filament::setCurrentPanel(Filament::getPanel('academic'));
        $student = $this->studentWithPhoto();

        Livewire::test(ListStudents::class)
            ->assertTableActionVisible('removeStudentPhoto', $student);
    }
}
