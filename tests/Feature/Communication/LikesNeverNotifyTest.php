<?php

namespace Tests\Feature\Communication;

use App\Models\User;
use App\Modules\Community\Actions\ToggleForumPostLikeAction;
use App\Modules\Community\Models\ForumPost;
use App\Modules\Institution\Models\Institution;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Likes no generan notificación individual (decisión confirmada del Hito
 * 5a): siguen siendo señal agregada dentro de Tracking, no una alerta en
 * tiempo real. Ningún listener de Communication cuelga de
 * ForumPostLiked/ForumPostUnliked -- solo los de Tracking.
 */
class LikesNeverNotifyTest extends TestCase
{
    use RefreshDatabase;

    public function test_liking_and_unliking_a_post_never_triggers_any_notification(): void
    {
        $this->seed(RolePermissionSeeder::class);
        Institution::factory()->create();
        $author = User::factory()->create()->assignRole('student');
        $liker = User::factory()->create()->assignRole('student');
        $post = ForumPost::factory()->create(['user_id' => $author->id]);

        Notification::fake();

        app(ToggleForumPostLikeAction::class)->execute($post, $liker);
        app(ToggleForumPostLikeAction::class)->execute($post, $liker);

        Notification::assertNothingSent();
    }
}
