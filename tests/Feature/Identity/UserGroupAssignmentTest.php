<?php

namespace Tests\Feature\Identity;

use App\Filament\Admin\Resources\Users\Pages\CreateUser;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Models\User;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\Group;
use App\Modules\Institution\Models\Institution;
use App\Modules\Institution\Models\SchoolGrade;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserGroupAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Cycle $cycle;

    protected SchoolGrade $schoolGrade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);

        $this->admin = User::factory()->create()->assignRole('super_admin');
        $this->actingAs($this->admin);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // order=1 explícito: test_group_select_filters_by_the_cycle_derived_from_the_chosen_grade
        // crea un segundo ciclo bajo la MISMA institución (order aleatorio
        // ya no es único entre sí -- CycleFactory dejó de usar
        // fake()->unique(), ver su docblock -- así que ambos deben fijarse
        // a mano para no chocar contra unique(['institution_id', 'order'])).
        $institution = Institution::factory()->create();
        $this->cycle = Cycle::factory()->for($institution)->create(['order' => 1]);
        $this->schoolGrade = SchoolGrade::factory()->for($institution)->for($this->cycle)->create();
    }

    protected function studentRoleId(): int
    {
        return Role::where('name', 'student')->value('id');
    }

    protected function teacherRoleId(): int
    {
        return Role::where('name', 'teacher')->value('id');
    }

    public function test_saving_a_student_with_a_group_persists_group_id_and_school_grade_id(): void
    {
        $group = Group::factory()->for($this->cycle)->create([
            'year' => config('school.current_academic_year'),
        ]);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Ana Estudiante',
                'email' => 'ana.estudiante@test.com',
                'password' => 'secret123',
                'roles' => [$this->studentRoleId()],
                'school_grade_id' => $this->schoolGrade->id,
                'group_id' => $group->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'ana.estudiante@test.com',
            'school_grade_id' => $this->schoolGrade->id,
            'group_id' => $group->id,
        ]);
    }

    public function test_group_select_filters_by_the_cycle_derived_from_the_chosen_grade(): void
    {
        $otherCycleInstitution = $this->schoolGrade->institution;
        $otherCycle = Cycle::factory()->for($otherCycleInstitution)->create(['order' => 2]);

        $sameCycleGroup = Group::factory()->for($this->cycle)->create([
            'name' => 'A',
            'year' => config('school.current_academic_year'),
        ]);

        $otherCycleGroup = Group::factory()->for($otherCycle)->create([
            'name' => 'B',
            'year' => config('school.current_academic_year'),
        ]);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'roles' => [$this->studentRoleId()],
                'school_grade_id' => $this->schoolGrade->id,
            ])
            ->assertFormFieldExists(
                'group_id',
                function (Select $field) use ($sameCycleGroup, $otherCycleGroup): bool {
                    $options = $field->getOptions();

                    return array_key_exists($sameCycleGroup->id, $options)
                        && ! array_key_exists($otherCycleGroup->id, $options);
                }
            );
    }

    public function test_group_select_filters_by_current_academic_year(): void
    {
        $currentYearGroup = Group::factory()->for($this->cycle)->create([
            'name' => 'A',
            'year' => config('school.current_academic_year'),
        ]);

        $otherYearGroup = Group::factory()->for($this->cycle)->create([
            'name' => 'B',
            'year' => ((int) config('school.current_academic_year')) + 1,
        ]);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'roles' => [$this->studentRoleId()],
                'school_grade_id' => $this->schoolGrade->id,
            ])
            ->assertFormFieldExists(
                'group_id',
                function (Select $field) use ($currentYearGroup, $otherYearGroup): bool {
                    $options = $field->getOptions();

                    return array_key_exists($currentYearGroup->id, $options)
                        && ! array_key_exists($otherYearGroup->id, $options);
                }
            );
    }

    public function test_editing_a_student_keeps_their_group_from_a_different_year_selectable(): void
    {
        $otherYear = ((int) config('school.current_academic_year')) + 1;

        $oldGroup = Group::factory()->for($this->cycle)->create([
            'name' => 'A',
            'year' => $otherYear,
        ]);

        $student = User::factory()->create([
            'school_grade_id' => $this->schoolGrade->id,
            'group_id' => $oldGroup->id,
        ]);
        $student->assignRole('student');

        Livewire::test(EditUser::class, ['record' => $student->getRouteKey()])
            ->assertFormSet(['school_grade_id' => $this->schoolGrade->id])
            ->assertFormFieldExists(
                'group_id',
                fn (Select $field): bool => array_key_exists($oldGroup->id, $field->getOptions())
            )
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'group_id' => $oldGroup->id,
        ]);
    }

    public function test_a_non_student_role_cannot_end_up_with_a_group_id(): void
    {
        $group = Group::factory()->for($this->cycle)->create([
            'year' => config('school.current_academic_year'),
        ]);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Profe Test',
                'email' => 'profe.test@test.com',
                'password' => 'secret123',
                'roles' => [$this->teacherRoleId()],
                // school_grade_id is set so group_id is among the field's
                // own valid options (otherwise Filament's Select would
                // silently null an out-of-options value before our rule
                // ever runs) — simulates a tampered request where a
                // non-student role is combined with a group assignment.
                'school_grade_id' => $this->schoolGrade->id,
                'group_id' => $group->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['group_id']);

        $this->assertDatabaseMissing('users', [
            'email' => 'profe.test@test.com',
        ]);
    }
}
