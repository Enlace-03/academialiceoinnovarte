<?php

namespace Tests\Feature\Community;

use App\Models\User;
use App\Modules\Community\Actions\ToggleForumPostLikeAction;
use App\Modules\Community\Models\ForumPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToggleForumPostLikeActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_call_likes_the_post(): void
    {
        $post = ForumPost::factory()->create();
        $user = User::factory()->create();

        $result = app(ToggleForumPostLikeAction::class)->execute($post, $user);

        $this->assertTrue($result);
        $this->assertSame(1, $post->likes()->count());
    }

    public function test_second_call_by_same_user_unlikes_it(): void
    {
        $post = ForumPost::factory()->create();
        $user = User::factory()->create();

        app(ToggleForumPostLikeAction::class)->execute($post, $user);
        $result = app(ToggleForumPostLikeAction::class)->execute($post, $user);

        $this->assertFalse($result);
        $this->assertSame(0, $post->likes()->count());
    }

    public function test_two_different_users_can_both_like_the_same_post(): void
    {
        $post = ForumPost::factory()->create();
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        app(ToggleForumPostLikeAction::class)->execute($post, $userA);
        app(ToggleForumPostLikeAction::class)->execute($post, $userB);

        $this->assertSame(2, $post->likes()->count());
    }
}
