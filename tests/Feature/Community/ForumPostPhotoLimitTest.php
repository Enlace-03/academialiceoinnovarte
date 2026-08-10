<?php

namespace Tests\Feature\Community;

use App\Models\User;
use App\Modules\Community\Actions\CreateForumPostAction;
use App\Modules\Community\Models\ForumThread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ForumPostPhotoLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_a_post_can_attach_up_to_the_maximum_number_of_photos(): void
    {
        $thread = ForumThread::factory()->create();
        $author = User::factory()->create();

        $photos = array_map(
            fn () => UploadedFile::fake()->image('foto.jpg', 200, 200),
            range(1, CreateForumPostAction::MAX_PHOTOS_PER_POST),
        );

        $post = app(CreateForumPostAction::class)->execute($thread, $author, [
            'content' => 'Mira estas fotos',
            'photos' => $photos,
        ]);

        $this->assertCount(CreateForumPostAction::MAX_PHOTOS_PER_POST, $post->photos);
    }

    public function test_a_post_cannot_attach_more_than_the_maximum_number_of_photos(): void
    {
        $thread = ForumThread::factory()->create();
        $author = User::factory()->create();

        $photos = array_map(
            fn () => UploadedFile::fake()->image('foto.jpg', 200, 200),
            range(1, CreateForumPostAction::MAX_PHOTOS_PER_POST + 1),
        );

        $this->expectException(ValidationException::class);

        app(CreateForumPostAction::class)->execute($thread, $author, [
            'content' => 'Demasiadas fotos',
            'photos' => $photos,
        ]);
    }

    public function test_photos_are_stored_in_order(): void
    {
        $thread = ForumThread::factory()->create();
        $author = User::factory()->create();

        $photos = [
            UploadedFile::fake()->image('primera.jpg', 100, 100),
            UploadedFile::fake()->image('segunda.jpg', 100, 100),
        ];

        $post = app(CreateForumPostAction::class)->execute($thread, $author, [
            'content' => 'Orden',
            'photos' => $photos,
        ]);

        $ordered = $post->photos()->orderBy('order')->pluck('original_filename')->all();

        $this->assertSame(['primera.jpg', 'segunda.jpg'], $ordered);
    }
}
