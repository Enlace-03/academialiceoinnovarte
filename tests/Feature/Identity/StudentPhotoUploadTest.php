<?php

namespace Tests\Feature\Identity;

use App\Livewire\Shared\PortalHome;
use App\Models\User;
use App\Modules\Identity\Actions\RemoveStudentPhotoAction;
use App\Modules\Identity\Actions\UploadStudentPhotoAction;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\SchoolGrade;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Lado acudiente de la foto de perfil de estudiante (ciclos 1-2 únicamente,
 * decisión confirmada). Autorización real vía UserPolicy (Gate::forUser
 * dentro de las Actions), no mockeada.
 */
class StudentPhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        Storage::fake('local');
    }

    private function studentInCycle(int $cycleOrder): User
    {
        $cycle = Cycle::factory()->create(['order' => $cycleOrder]);
        $grade = SchoolGrade::factory()->create(['cycle_id' => $cycle->id]);

        return User::factory()->create(['school_grade_id' => $grade->id])->assignRole('student');
    }

    /**
     * Construye un JPEG real con un segmento APP1/Exif válido a mano
     * (GD no escribe Exif al codificar, así que no hay forma de generar un
     * "insumo con metadata real" sin construir los bytes) -- un único tag
     * ASCII (0x010F, Make) alcanza para confirmar que exif_read_data() lo ve
     * en el origen y ya no lo ve en el resultado final. $make debe entrar en
     * 4 bytes junto con su terminador nulo -- el IFD de abajo solo escribe el
     * valor inline (nunca por offset externo), que es todo lo que un tag de
     * este tamaño necesita.
     */
    private function jpegBytesWithExifMake(string $make): string
    {
        $image = imagecreatetruecolor(900, 600);
        mt_srand(11);
        for ($i = 0; $i < 1500; $i++) {
            imagesetpixel($image, mt_rand(0, 899), mt_rand(0, 599), imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
        }
        ob_start();
        imagejpeg($image, null, 95);
        $base = ob_get_clean();
        imagedestroy($image);

        $makeBytes = $make."\0";
        $count = strlen($makeBytes);
        $valueField = str_pad($makeBytes, 4, "\0");

        $ifd0 = "\x01\x00" // 1 entrada
            ."\x0F\x01" // tag 0x010F (Make)
            ."\x02\x00" // type ASCII
            .pack('V', $count)
            .$valueField
            ."\x00\x00\x00\x00"; // próximo IFD: ninguno

        $tiffHeader = "II\x2A\x00".pack('V', 8);
        $payload = "Exif\x00\x00".$tiffHeader.$ifd0;
        $app1 = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

        return substr($base, 0, 2).$app1.substr($base, 2);
    }

    public function test_guardian_of_cycle_1_2_child_can_upload_change_and_remove_photo(): void
    {
        $guardian = User::factory()->create()->assignRole('parent');
        $student = $this->studentInCycle(1);
        $guardian->children()->attach($student->id, ['relationship' => 'madre']);

        app(UploadStudentPhotoAction::class)->execute($guardian, $student, UploadedFile::fake()->image('foto.jpg', 800, 800));

        $student->refresh();
        $this->assertTrue($student->hasPhoto());
        $firstPath = $student->photo_path;
        Storage::disk('local')->assertExists($firstPath);

        // Cambiar: mismo path determinístico (uuid del estudiante), sobrescribe.
        app(UploadStudentPhotoAction::class)->execute($guardian, $student, UploadedFile::fake()->image('foto2.jpg', 800, 800));
        $student->refresh();
        $this->assertSame($firstPath, $student->photo_path);
        Storage::disk('local')->assertExists($student->photo_path);

        // Quitar.
        app(RemoveStudentPhotoAction::class)->execute($guardian, $student);
        $student->refresh();
        $this->assertFalse($student->hasPhoto());
        Storage::disk('local')->assertMissing($firstPath);
    }

    public function test_guardian_cannot_upload_photo_of_a_student_that_is_not_their_child(): void
    {
        $guardian = User::factory()->create()->assignRole('parent');
        $unrelatedStudent = $this->studentInCycle(1);

        $this->expectException(AuthorizationException::class);

        app(UploadStudentPhotoAction::class)->execute($guardian, $unrelatedStudent, UploadedFile::fake()->image('foto.jpg'));
    }

    public function test_guardian_cannot_remove_photo_of_a_student_that_is_not_their_child(): void
    {
        $guardian = User::factory()->create()->assignRole('parent');
        $unrelatedStudent = $this->studentInCycle(1);

        $this->expectException(AuthorizationException::class);

        app(RemoveStudentPhotoAction::class)->execute($guardian, $unrelatedStudent);
    }

    public function test_guardian_cannot_upload_photo_for_a_cycle_3_4_child(): void
    {
        $guardian = User::factory()->create()->assignRole('parent');
        $student = $this->studentInCycle(3);
        $guardian->children()->attach($student->id, ['relationship' => 'padre']);

        $this->expectException(AuthorizationException::class);

        app(UploadStudentPhotoAction::class)->execute($guardian, $student, UploadedFile::fake()->image('foto.jpg'));
    }

    public function test_upload_is_rejected_with_a_clear_message_when_blocked(): void
    {
        $guardian = User::factory()->create()->assignRole('parent');
        $student = $this->studentInCycle(2);
        $guardian->children()->attach($student->id, ['relationship' => 'madre']);
        $student->forceFill(['photo_upload_blocked' => true])->save();

        try {
            app(UploadStudentPhotoAction::class)->execute($guardian, $student, UploadedFile::fake()->image('foto.jpg'));
            $this->fail('Se esperaba ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('photo', $e->errors());
        }

        $student->refresh();
        $this->assertFalse($student->hasPhoto());
    }

    public function test_photo_is_compressed_to_500px_and_strips_exif_metadata(): void
    {
        $guardian = User::factory()->create()->assignRole('parent');
        $student = $this->studentInCycle(1);
        $guardian->children()->attach($student->id, ['relationship' => 'madre']);

        $sourceBytes = $this->jpegBytesWithExifMake('ABC');
        $tmpPath = tempnam(sys_get_temp_dir(), 'photo').'.jpg';
        file_put_contents($tmpPath, $sourceBytes);

        $sourceExif = @exif_read_data($tmpPath);
        $this->assertIsArray($sourceExif);
        $this->assertSame('ABC', $sourceExif['Make'] ?? null);

        $file = new UploadedFile($tmpPath, 'foto.jpg', 'image/jpeg', null, true);

        app(UploadStudentPhotoAction::class)->execute($guardian, $student, $file);

        $student->refresh();
        $finalAbsolutePath = Storage::disk('local')->path($student->photo_path);

        $this->assertLessThan(strlen($sourceBytes), filesize($finalAbsolutePath));

        $dimensions = getimagesize($finalAbsolutePath);
        $this->assertSame(500, $dimensions[0]);

        $finalExif = @exif_read_data($finalAbsolutePath);
        $this->assertTrue($finalExif === false || ! array_key_exists('Make', $finalExif));
    }

    /**
     * El caso real que motivó este test: una foto bajo el límite de 8KB de
     * Livewire (por eso pasa el $this->validate() del primer paso) pero con
     * dimensiones de pixel demasiado grandes para procesarse con la memoria
     * disponible -- justo lo que CompressUploadedImageAction rechaza con su
     * propia ValidationException, bajo la clave 'photo', no 'photoUploads.
     * {id}'. Antes de este fix esa excepción se perdía sin ningún mensaje
     * visible en la tarjeta del hijo correcto; PortalHome::uploadPhoto()
     * ahora la re-lanza remapeada. Mismo generador de JPEG "grande en
     * píxeles, chico en bytes" que PhotoCompressionTest (relleno sólido).
     */
    public function test_shows_a_clear_error_when_the_action_rejects_the_upload(): void
    {
        $guardian = User::factory()->create()->assignRole('parent');
        $student = $this->studentInCycle(1);
        $guardian->children()->attach($student->id, ['relationship' => 'madre']);

        $image = imagecreatetruecolor(7000, 6000);
        imagefill($image, 0, 0, imagecolorallocate($image, 128, 128, 128));
        ob_start();
        imagejpeg($image, null, 90);
        $binary = ob_get_clean();
        imagedestroy($image);

        $tmpPath = tempnam(sys_get_temp_dir(), 'photo').'.jpg';
        file_put_contents($tmpPath, $binary);
        $this->assertLessThan(8192 * 1024, filesize($tmpPath));

        $file = new UploadedFile($tmpPath, 'foto-enorme.jpg', 'image/jpeg', null, true);

        // ->set() con un Illuminate\Http\UploadedFile crudo no aplica acá:
        // Livewire espera el objeto ya resuelto por su propio mecanismo de
        // subida (TemporaryUploadedFile) para esa vía. Se asigna la
        // propiedad directo sobre la instancia -- exactamente el estado en
        // el que ya está $this->photoUploads[$id] en un request real para
        // cuando uploadPhoto() corre, sin re-probar el transporte de subida
        // de Livewire (no es lo que este test verifica).
        $component = Livewire::actingAs($guardian)->test(PortalHome::class);
        $component->instance()->photoUploads[$student->id] = $file;

        $component->call('uploadPhoto', $student->id)
            ->assertHasErrors(["photoUploads.{$student->id}"]);

        $student->refresh();
        $this->assertFalse($student->hasPhoto());
    }
}
