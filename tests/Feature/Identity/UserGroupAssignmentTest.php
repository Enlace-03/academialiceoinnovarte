<?php

namespace Tests\Feature\Identity;

use App\Filament\Admin\Resources\Users\Pages\CreateUser;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Models\User;
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

    protected SchoolGrade $schoolGrade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);

        $this->admin = User::factory()->create()->assignRole('super_admin');
        $this->actingAs($this->admin);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $institution = Institution::factory()->create();
        $this->schoolGrade = SchoolGrade::factory()->for($institution)->create();
    }

    protected function studentRoleId(): int
    {
        return Role::where('name', 'student')->value('id');
    }

    protected function teacherRoleId(): int
    {
        return Role::where('name', 'teacher')->value('id');
    }

    public function test_saving_a_student_with_a_group_persists_group_id(): void
    {
        $group = Group::factory()->for($this->schoolGrade)->create([
            'year' => config('school.current_academic_year'),
        ]);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Ana Estudiante',
                'email' => 'ana.estudiante@test.com',
                'password' => 'secret123',
                'roles' => [$this->studentRoleId()],
                'school_grade_filter' => $this->schoolGrade->id,
                'group_id' => $group->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'ana.estudiante@test.com',
            'group_id' => $group->id,
        ]);
    }

    public function test_group_select_filters_by_current_academic_year(): void
    {
        $currentYearGroup = Group::factory()->for($this->schoolGrade)->create([
            'name' => 'A',
            'year' => config('school.current_academic_year'),
        ]);

        $otherYearGroup = Group::factory()->for($this->schoolGrade)->create([
            'name' => 'B',
            'year' => ((int) config('school.current_academic_year')) + 1,
        ]);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'roles' => [$this->studentRoleId()],
                'school_grade_filter' => $this->schoolGrade->id,
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

        $oldGroup = Group::factory()->for($this->schoolGrade)->create([
            'name' => 'A',
            'year' => $otherYear,
        ]);

        $student = User::factory()->create(['group_id' => $oldGroup->id]);
        $student->assignRole('student');

        Livewire::test(EditUser::class, ['record' => $student->getRouteKey()])
            ->assertFormSet(['school_grade_filter' => $this->schoolGrade->id])
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
        $group = Group::factory()->for($this->schoolGrade)->create([
            'year' => config('school.current_academic_year'),
        ]);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Profe Test',
                'email' => 'profe.test@test.com',
                'password' => 'secret123',
                'roles' => [$this->teacherRoleId()],
                // school_grade_filter is set so group_id is among the field's
                // own valid options (otherwise Filament's Select would
                // silently null an out-of-options value before our rule
                // ever runs) — simulates a tampered request where a
                // non-student role is combined with a group assignment.
                'school_grade_filter' => $this->schoolGrade->id,
                'group_id' => $group->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['group_id']);

        $this->assertDatabaseMissing('users', [
            'email' => 'profe.test@test.com',
        ]);
    }
}
