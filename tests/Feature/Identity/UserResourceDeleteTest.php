<?php

namespace Tests\Feature\Identity;

use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Models\User;
use App\Modules\Assessment\Models\ReportCard;
use App\Modules\Community\Actions\UserHasCommunityContentAction;
use App\Modules\Community\Models\ChatMessage;
use App\Modules\Community\Models\ForumPost;
use App\Modules\Community\Models\ForumThread;
use App\Modules\Community\Models\PrivateChatMessage;
use App\Modules\Community\Models\PrivateChatThread;
use App\Modules\Institution\Models\Group;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * chat_messages/private_chat_messages/forum_posts/forum_threads pasaron de
 * cascadeOnDelete() a restrictOnDelete() (migración 2027_01_01_000450) --
 * borrar un usuario con actividad de comunidad es historial institucional,
 * no algo que deba desaparecer junto con la cuenta. Este test confirma que
 * la UI captura eso ANTES de la BD (notificación amigable) en vez de dejar
 * pasar la excepción SQL cruda.
 */
class UserResourceDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);

        $this->admin = User::factory()->create()->assignRole('super_admin');
        $this->actingAs($this->admin);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_a_user_without_community_content_can_be_deleted(): void
    {
        $user = User::factory()->create()->assignRole('teacher');

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->callAction(DeleteAction::class);

        $this->assertModelMissing($user);
    }

    public function test_a_user_with_a_chat_message_cannot_be_deleted(): void
    {
        $group = Group::factory()->create();
        $student = User::factory()->create(['group_id' => $group->id])->assignRole('student');
        ChatMessage::factory()->create(['group_id' => $group->id, 'user_id' => $student->id]);

        Livewire::test(EditUser::class, ['record' => $student->getRouteKey()])
            ->callAction(DeleteAction::class)
            ->assertNotified('No se puede eliminar: este usuario tiene contenido asociado: mensajes, publicaciones o boletines. Desactívalo en su lugar.');

        $this->assertModelExists($student);
    }

    public function test_the_friendly_message_is_shown_and_the_user_survives_when_the_precheck_misses_a_race(): void
    {
        $group = Group::factory()->create();
        $student = User::factory()->create(['group_id' => $group->id])->assignRole('student');
        ChatMessage::factory()->create(['group_id' => $group->id, 'user_id' => $student->id]);

        // Simula la carrera: el chequeo previo dice "sin contenido" pero la FK
        // restrictiva sí dispara al ejecutar el DELETE.
        $this->app->instance(UserHasCommunityContentAction::class, new class
        {
            public function execute(User $user): bool
            {
                return false;
            }
        });

        Livewire::test(EditUser::class, ['record' => $student->getRouteKey()])
            ->callAction(DeleteAction::class)
            ->assertNotified('No se puede eliminar: este usuario tiene contenido asociado: mensajes, publicaciones o boletines. Desactívalo en su lugar.');

        $this->assertModelExists($student);
    }

    /**
     * report_cards.student_id usa restrictOnDelete(), pero el chequeo previo
     * (UserHasCommunityContentAction) no mira boletines: este caso llega hasta
     * la FK y lo captura el catch de using(), con el mismo mensaje.
     */
    public function test_a_student_with_a_report_card_cannot_be_deleted_and_gets_the_friendly_message(): void
    {
        Storage::fake('local');

        $student = User::factory()->create()->assignRole('student');
        ReportCard::factory()->create(['student_id' => $student->id]);

        Livewire::test(EditUser::class, ['record' => $student->getRouteKey()])
            ->callAction(DeleteAction::class)
            ->assertNotified('No se puede eliminar: este usuario tiene contenido asociado: mensajes, publicaciones o boletines. Desactívalo en su lugar.');

        $this->assertModelExists($student);
        $this->assertSame(1, ReportCard::count());
    }

    public function test_a_user_with_a_forum_post_cannot_be_deleted(): void
    {
        $author = User::factory()->create()->assignRole('student');
        $thread = ForumThread::factory()->create();
        ForumPost::factory()->create(['forum_thread_id' => $thread->id, 'user_id' => $author->id]);

        Livewire::test(EditUser::class, ['record' => $author->getRouteKey()])
            ->callAction(DeleteAction::class)
            ->assertNotified();

        $this->assertModelExists($author);
    }

    public function test_a_user_who_created_a_forum_thread_cannot_be_deleted(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        ForumThread::factory()->create(['created_by' => $teacher->id]);

        Livewire::test(EditUser::class, ['record' => $teacher->getRouteKey()])
            ->callAction(DeleteAction::class)
            ->assertNotified();

        $this->assertModelExists($teacher);
    }

    public function test_a_user_with_a_private_chat_message_cannot_be_deleted(): void
    {
        $student = User::factory()->create()->assignRole('student');
        $thread = PrivateChatThread::factory()->create(['student_id' => $student->id]);
        PrivateChatMessage::factory()->create(['thread_id' => $thread->id, 'user_id' => $student->id]);

        Livewire::test(EditUser::class, ['record' => $student->getRouteKey()])
            ->callAction(DeleteAction::class)
            ->assertNotified();

        $this->assertModelExists($student);
    }
}
