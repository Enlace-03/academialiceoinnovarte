<?php

namespace Tests\Feature\Community;

use App\Modules\Community\Models\ForumPost;
use App\Modules\Community\Models\ForumPostPhoto;
use App\Modules\Community\Models\GalleryPhoto;
use App\Modules\Community\Models\GalleryPost;
use App\Modules\Shared\Actions\CompressUploadedImageAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Prueba la compresión REAL (CompressesPhotoUploads -> CompressUploadedImageAction
 * -> Intervention Image), no un mock -- genera un JPEG grande de verdad vía GD
 * (con ruido, para que no comprima artificialmente bien como un color plano) y
 * confirma que el archivo final en disco pesa menos y mide como máximo 1600px
 * de ancho.
 */
class PhotoCompressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function putLargeJpeg(string $path): int
    {
        $image = imagecreatetruecolor(2400, 1600);
        mt_srand(7);

        for ($i = 0; $i < 3000; $i++) {
            imagesetpixel($image, mt_rand(0, 2399), mt_rand(0, 1599), imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
        }

        ob_start();
        imagejpeg($image, null, 100);
        $binary = ob_get_clean();
        imagedestroy($image);

        Storage::disk('local')->put($path, $binary);

        return Storage::disk('local')->size($path);
    }

    public function test_gallery_photo_is_compressed_and_resized_on_creation(): void
    {
        $originalSize = $this->putLargeJpeg('gallery-photos/source.jpg');

        $post = GalleryPost::factory()->create();
        $photo = GalleryPhoto::factory()->create([
            'gallery_post_id' => $post->id,
            'file_disk' => 'local',
            'file_path' => 'gallery-photos/source.jpg',
        ]);
        $photo->refresh();

        $finalSize = Storage::disk('local')->size($photo->file_path);
        $dimensions = getimagesize(Storage::disk('local')->path($photo->file_path));

        $this->assertLessThan($originalSize, $finalSize);
        $this->assertLessThanOrEqual(1600, $dimensions[0]);
        $this->assertSame('image/jpeg', $dimensions['mime']);
        $this->assertStringEndsWith('.jpg', $photo->file_path);
        $this->assertFalse(Storage::disk('local')->exists('gallery-photos/source.jpg'));
    }

    public function test_forum_post_photo_is_compressed_and_resized_on_creation(): void
    {
        $originalSize = $this->putLargeJpeg('forum-photos/source.jpg');

        $post = ForumPost::factory()->create();
        $photo = ForumPostPhoto::factory()->create([
            'forum_post_id' => $post->id,
            'file_disk' => 'local',
            'file_path' => 'forum-photos/source.jpg',
        ]);
        $photo->refresh();

        $finalSize = Storage::disk('local')->size($photo->file_path);
        $dimensions = getimagesize(Storage::disk('local')->path($photo->file_path));

        $this->assertLessThan($originalSize, $finalSize);
        $this->assertLessThanOrEqual(1600, $dimensions[0]);
    }

    /**
     * No depende de que el hosting real otorgue 512M (ver el comentario en
     * CompressUploadedImageAction sobre por qué eso está pendiente de
     * confirmar contra cPanel): una imagen cuyas dimensiones declaradas
     * exceden lo que CUALQUIER memory_limit razonable (incluido el mejor
     * caso, 512M, que es lo máximo que el Action intenta pedir) puede
     * procesar con margen de seguridad debe rechazarse con un error de
     * validación común, nunca con un Fatal Error de PHP no capturable.
     */
    public function test_rejects_a_photo_too_large_to_process_safely_instead_of_crashing(): void
    {
        $image = imagecreatetruecolor(7000, 6000);
        imagefill($image, 0, 0, imagecolorallocate($image, 128, 128, 128));
        ob_start();
        imagejpeg($image, null, 90);
        $binary = ob_get_clean();
        imagedestroy($image);

        Storage::disk('local')->put('gallery-photos/huge.jpg', $binary);

        $this->expectException(ValidationException::class);

        app(CompressUploadedImageAction::class)->execute(
            'local',
            'gallery-photos/huge.jpg',
            'gallery-photos/huge-target.jpg',
        );
    }

    public function test_deleting_a_photo_removes_its_physical_file(): void
    {
        $post = GalleryPost::factory()->create();
        $photo = GalleryPhoto::factory()->create(['gallery_post_id' => $post->id]);
        $photo->refresh();

        $storedPath = $photo->file_path;
        $this->assertTrue(Storage::disk('local')->exists($storedPath));

        $photo->delete();

        $this->assertFalse(Storage::disk('local')->exists($storedPath));
    }
}
