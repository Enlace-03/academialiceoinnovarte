<?php

namespace Tests\Feature\Identity;

use App\Filament\Admin\Resources\Users\Pages\CreateUser;
use App\Models\User;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Hito de permisos, corrección #2: students.create es ahora una vía
 * ADICIONAL (más angosta) hacia UserPolicy::create() -- no reemplaza
 * users.create, se suma. Quien entra solo por esta vía nunca debe ver ni
 * poder asignar un rol de personal (teacher/coordinator/rector/secretary/
 * super_admin) -- el desplegable de rol en UserForm se filtra vía
 * HasDelegationCeiling::assignableRoles(), no solo la validación de guardado.
 */
class StudentsCreatePermissionUserCreationTest extends TestCase
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

    protected function roleId(string $name): int
    {
        return Role::where('name', $name)->value('id');
    }

    protected function baseFormData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Usuario Prueba',
            'email' => 'usuario.prueba@test.com',
            'password' => 'secret123',
        ], $overrides);
    }

    // -- Acceso al panel: students.create debe ser suficiente por sí solo --

    public function test_a_user_with_only_students_create_can_access_the_admin_panel(): void
    {
        $actor = $this->actorWithOnlyStudentsCreate();

        $this->assertTrue($actor->canAccessPanel(Panel::make()->id('admin')));
    }

    public function test_a_user_with_no_relevant_permission_cannot_access_the_admin_panel(): void
    {
        $actor = User::factory()->create();

        $this->assertFalse($actor->canAccessPanel(Panel::make()->id('admin')));
    }

    // -- UserPolicy::create(): vía adicional, no reemplazo --

    public function test_a_user_with_only_students_create_passes_the_create_policy(): void
    {
        $actor = $this->actorWithOnlyStudentsCreate();

        $this->assertTrue($actor->can('create', User::class));
    }

    public function test_a_user_with_neither_permission_fails_the_create_policy(): void
    {
        $actor = User::factory()->create();

        $this->assertFalse($actor->can('create', User::class));
    }

    // -- Filtrado de roles asignables (HasDelegationCeiling) --

    public function test_only_students_create_narrows_assignable_roles_to_student_and_parent(): void
    {
        $actor = $this->actorWithOnlyStudentsCreate();

        $names = $actor->assignableRoles()->pluck('name')->sort()->values()->all();

        $this->assertSame(['parent', 'student'], $names);
    }

    public function test_a_user_with_full_users_create_still_sees_every_delegable_role(): void
    {
        // secretary ya tiene users.create pleno en su preset -- comportamiento
        // sin cambios, no pasa por la vía angosta de students.create.
        $secretary = User::factory()->create()->assignRole('secretary');

        $names = $secretary->assignableRoles()->pluck('name')->all();

        // secretary no tiene techo para otorgar coordinator/rector/teacher
        // (no están dentro de su propio conjunto de permisos), pero SÍ debe
        // seguir viendo student, parent y secretary -- la vía angosta no
        // debe activarse ni recortar nada para ella.
        $this->assertContains('student', $names);
        $this->assertContains('parent', $names);
        $this->assertContains('secretary', $names);
    }

    // -- El propio formulario de Filament: el desplegable no debe ni mostrar
    //    las opciones fuera del alcance, no solo bloquear el guardado. --

    public function test_the_role_select_only_offers_student_and_parent_for_a_students_create_only_actor(): void
    {
        $actor = $this->actorWithOnlyStudentsCreate();
        $this->actingAs($actor);

        $response = Livewire::test(CreateUser::class);
        $field = $response->instance()->form->getFlatFields(withHidden: true)['roles'];

        $results = $field->getSearchResultsForJs('');
        $labels = array_column($results, 'label');

        $this->assertNotEmpty($labels);
        foreach ($labels as $label) {
            $this->assertContains($label, ['Estudiante', 'Acudiente']);
        }
    }

    public function test_the_role_select_still_offers_every_role_for_a_full_users_create_actor(): void
    {
        // secretary ya tenía users.create pleno en su preset desde antes de
        // este hito -- no pasa por la vía angosta de students.create, así
        // que su desplegable de rol no debe verse recortado por este cambio.
        $secretary = User::factory()->create()->assignRole('secretary');
        $this->actingAs($secretary);

        $response = Livewire::test(CreateUser::class);
        $field = $response->instance()->form->getFlatFields(withHidden: true)['roles'];

        $labels = array_column($field->getSearchResultsForJs(''), 'label');

        // No se recorta a solo Estudiante/Acudiente (lo que sí pasaría si
        // students.create -- que secretary también tiene -- disparara por
        // error la vía angosta para alguien que YA tiene users.create).
        $this->assertGreaterThan(2, count($labels));
        $this->assertContains('Secretaría', $labels);
    }

    // -- Flujo completo end-to-end contra el formulario real --

    public function test_a_students_create_only_actor_can_create_a_student(): void
    {
        $actor = $this->actorWithOnlyStudentsCreate();
        $this->actingAs($actor);

        Livewire::test(CreateUser::class)
            ->fillForm($this->baseFormData(['roles' => [$this->roleId('student')]]))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', ['email' => 'usuario.prueba@test.com']);
        $this->assertTrue(User::where('email', 'usuario.prueba@test.com')->first()->hasRole('student'));
    }

    public function test_a_students_create_only_actor_can_create_a_parent(): void
    {
        $actor = $this->actorWithOnlyStudentsCreate();
        $this->actingAs($actor);

        Livewire::test(CreateUser::class)
            ->fillForm($this->baseFormData(['roles' => [$this->roleId('parent')]]))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', ['email' => 'usuario.prueba@test.com']);
        $this->assertTrue(User::where('email', 'usuario.prueba@test.com')->first()->hasRole('parent'));
    }

    public function test_a_students_create_only_actor_is_rejected_creating_a_teacher(): void
    {
        $actor = $this->actorWithOnlyStudentsCreate();
        $this->actingAs($actor);

        Livewire::test(CreateUser::class)
            ->fillForm($this->baseFormData(['roles' => [$this->roleId('teacher')]]))
            ->call('create')
            ->assertHasFormErrors(['roles']);

        $this->assertDatabaseMissing('users', ['email' => 'usuario.prueba@test.com']);
    }

    /**
     * Defensa en profundidad: aunque alguien manipulara el request para
     * enviar un role id fuera del select ya filtrado, la Policy/regla del
     * lado servidor lo sigue rechazando -- no es solo la UI la que protege.
     */
    public function test_a_students_create_only_actor_is_rejected_creating_a_super_admin_even_bypassing_the_select(): void
    {
        $actor = $this->actorWithOnlyStudentsCreate();
        $this->actingAs($actor);

        Livewire::test(CreateUser::class)
            ->fillForm($this->baseFormData(['roles' => [$this->roleId('super_admin')]]))
            ->call('create')
            ->assertHasFormErrors(['roles']);

        $this->assertDatabaseMissing('users', ['email' => 'usuario.prueba@test.com']);
    }
}
