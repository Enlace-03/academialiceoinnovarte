<?php

namespace Tests\Feature\Identity;

use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Admin\Resources\Users\RelationManagers\GuardiansRelationManager;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\User;
use App\Modules\Identity\Models\DataTreatmentConsent;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Hito de permisos, corrección #3: el mismo patrón de alcance angosto de
 * students.create (correcciones #1/#2) se extiende a EDITAR usuarios --
 * necesario para llegar a GuardiansRelationManager (vive en la página de
 * edición, no en la de creación) y vincular un acudiente. Quien entra solo
 * por esta vía puede editar ÚNICAMENTE student/parent -- nunca teacher/
 * coordinator/rector/secretary/super_admin, verificado explícitamente.
 */
class StudentsCreatePermissionUserEditingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function actorWithOnlyStudentsCreate(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('students.create');

        return $user;
    }

    // -- UserPolicy::update(): vía adicional, no reemplazo --

    public function test_a_students_create_only_actor_passes_the_update_policy_for_a_student(): void
    {
        $actor = $this->actorWithOnlyStudentsCreate();
        $student = User::factory()->create()->assignRole('student');

        $this->assertTrue($actor->can('update', $student));
    }

    public function test_a_students_create_only_actor_passes_the_update_policy_for_a_parent(): void
    {
        $actor = $this->actorWithOnlyStudentsCreate();
        $parent = User::factory()->create()->assignRole('parent');

        $this->assertTrue($actor->can('update', $parent));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function staffRoles(): array
    {
        return [
            'teacher' => ['teacher'],
            'coordinator' => ['coordinator'],
            'rector' => ['rector'],
            'secretary' => ['secretary'],
            'super_admin' => ['super_admin'],
        ];
    }

    #[DataProvider('staffRoles')]
    public function test_a_students_create_only_actor_fails_the_update_policy_for_staff_roles(string $role): void
    {
        $actor = $this->actorWithOnlyStudentsCreate();
        $target = User::factory()->create()->assignRole($role);

        $this->assertFalse($actor->can('update', $target));
    }

    public function test_a_user_with_full_users_update_is_unaffected(): void
    {
        $rector = User::factory()->create()->assignRole('rector');
        // El conjunto de permisos de secretary es subconjunto pleno del de
        // rector (a diferencia de teacher, que usa projects.*.own en vez de
        // projects.*.all -- canManageUser() los ve como distintos aunque
        // semánticamente rector tenga más autoridad, subtileza preexistente
        // y ajena a este cambio).
        $secretary = User::factory()->create()->assignRole('secretary');

        $this->assertTrue($rector->can('update', $secretary));
    }

    /**
     * canManageUser() ya dejaba pasar a student/parent como objetivo para
     * CUALQUIER actor -- no cargan ningún permiso del catálogo (fixed
     * roles), así que el diff contra el propio conjunto de permisos del
     * actor siempre da vacío. No hizo falta agregarle una rama nueva (a
     * diferencia de assignableRoles() en la corrección #2) -- este test lo
     * deja confirmado en vez de asumirlo.
     */
    public function test_can_manage_user_already_allows_any_actor_to_manage_a_student_or_parent_target(): void
    {
        $actorWithNoPermissionsAtAll = User::factory()->create();
        $student = User::factory()->create()->assignRole('student');

        $this->assertTrue($actorWithNoPermissionsAtAll->canManageUser($student));
    }

    // -- Acceso HTTP real a la página de edición (no solo la Policy aislada) --

    public function test_a_students_create_only_actor_can_open_the_edit_page_of_a_student(): void
    {
        $actor = $this->actorWithOnlyStudentsCreate();
        $this->actingAs($actor);

        $student = User::factory()->create()->assignRole('student');

        $response = $this->get(UserResource::getUrl('edit', ['record' => $student]));

        $response->assertOk();
    }

    public function test_a_students_create_only_actor_can_open_the_edit_page_of_a_parent(): void
    {
        $actor = $this->actorWithOnlyStudentsCreate();
        $this->actingAs($actor);

        $parent = User::factory()->create()->assignRole('parent');

        $response = $this->get(UserResource::getUrl('edit', ['record' => $parent]));

        $response->assertOk();
    }

    #[DataProvider('staffRoles')]
    public function test_a_students_create_only_actor_is_forbidden_from_the_edit_page_of_staff_roles(string $role): void
    {
        $actor = $this->actorWithOnlyStudentsCreate();
        $this->actingAs($actor);

        $target = User::factory()->create()->assignRole($role);

        $response = $this->get(UserResource::getUrl('edit', ['record' => $target]));

        $response->assertForbidden();
    }

    // -- Flujo completo: abrir la edición y vincular un acudiente de punta a punta --

    public function test_a_students_create_only_actor_can_link_a_guardian_end_to_end(): void
    {
        $actor = $this->actorWithOnlyStudentsCreate();
        $this->actingAs($actor);

        $student = User::factory()->create()->assignRole('student');
        $parent = User::factory()->create()->assignRole('parent');

        // La página real primero -- confirma que no hay ningún 403 en el
        // camino antes de llegar al RelationManager.
        $this->get(UserResource::getUrl('edit', ['record' => $student]))->assertOk();

        Livewire::test(GuardiansRelationManager::class, [
            'ownerRecord' => $student,
            'pageClass' => EditUser::class,
        ])
            ->mountTableAction('attach')
            ->setTableActionData([
                'recordId' => $parent->id,
                'relationship' => 'madre',
                'is_primary_contact' => true,
                'data_treatment_consent' => true,
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('parent_student', [
            'parent_id' => $parent->id,
            'student_id' => $student->id,
            'relationship' => 'madre',
        ]);

        $consent = DataTreatmentConsent::firstOrFail();
        $this->assertTrue($consent->confirmedBy->is($actor));
    }
}
