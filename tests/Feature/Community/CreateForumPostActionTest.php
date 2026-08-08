<?php

namespace Tests\Feature\Community;

use App\Models\User;
use App\Modules\Community\Actions\CreateForumPostAction;
use App\Modules\Community\Models\ForumPost;
use App\Modules\Community\Models\ForumThread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreateForumPostActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_root_post(): void
    {
        $thread = ForumThread::factory()->create();
        $author = User::factory()->create();

        $post = app(CreateForumPostAction::class)->execute($thread, $author, ['content' => 'Hola']);

        $this->assertNull($post->parent_post_id);
        $this->assertSame('Hola', $post->content);
    }

    public function test_creates_a_reply_to_a_root_post(): void
    {
        $thread = ForumThread::factory()->create();
        $rootPost = ForumPost::factory()->create(['forum_thread_id' => $thread->id]);
        $author = User::factory()->create();

        $reply = app(CreateForumPostAction::class)->execute($thread, $author, [
            'content' => 'Respuesta',
            'parent_post_id' => $rootPost->id,
        ]);

        $this->assertSame($rootPost->id, $reply->parent_post_id);
    }

    public function test_rejects_a_reply_to_a_reply(): void
    {
        $thread = ForumThread::factory()->create();
        $rootPost = ForumPost::factory()->create(['forum_thread_id' => $thread->id]);
        $reply = ForumPost::factory()->create([
            'forum_thread_id' => $thread->id,
            'parent_post_id' => $rootPost->id,
        ]);
        $author = User::factory()->create();

        $this->expectException(ValidationException::class);

        app(CreateForumPostAction::class)->execute($thread, $author, [
            'content' => 'Respuesta a una respuesta',
            'parent_post_id' => $reply->id,
        ]);
    }

    public function test_rejects_a_reply_to_a_hidden_post(): void
    {
        $thread = ForumThread::factory()->create();
        $hiddenPost = ForumPost::factory()->create([
            'forum_thread_id' => $thread->id,
            'is_hidden' => true,
        ]);
        $author = User::factory()->create();

        $this->expectException(ValidationException::class);

        app(CreateForumPostAction::class)->execute($thread, $author, [
            'content' => 'Respuesta a un post oculto',
            'parent_post_id' => $hiddenPost->id,
        ]);
    }
}
