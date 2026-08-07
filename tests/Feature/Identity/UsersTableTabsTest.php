<?php

namespace Tests\Feature\Identity;

use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Models\User;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\Group;
use App\Modules\Institution\Models\Institution;
use App\Modules\Institution\Models\SchoolGrade;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UsersTableTabsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $rector;

    protected User $student;

    protected User $guardian;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create()->assignRole('super_admin');
        $this->rector = User::factory()->create()->assignRole('rector');
        $this->student = User::factory()->create()->assignRole('student');
        $this->guardian = User::factory()->create()->assignRole('parent');

        $this->actingAs($this->admin);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_personal_is_the_default_tab_and_shows_only_staff_users(): void
    {
        Livewire::test(ListUsers::class)
            ->assertSet('activeTab', 'staff')
            ->assertCanSeeTableRecords([$this->admin, $this->rector])
            ->assertCanNotSeeTableRecords([$this->student, $this->guardian])
            ->assertCountTableRecords(2);
    }

    public function test_estudiantes_tab_shows_only_student_users(): void
    {
        Livewire::test(ListUsers::class)
            ->set('activeTab', 'students')
            ->assertCanSeeTableRecords([$this->student])
            ->assertCanNotSeeTableRecords([$this->admin, $this->rector, $this->guardian])
            ->assertCountTableRecords(1);
    }

    public function test_acudientes_tab_shows_only_parent_users(): void
    {
        Livewire::test(ListUsers::class)
            ->set('activeTab', 'guardians')
            ->assertCanSeeTableRecords([$this->guardian])
            ->assertCanNotSeeTableRecords([$this->admin, $this->rector, $this->student])
            ->assertCountTableRecords(1);
    }

    public function test_tab_badges_reflect_the_correct_counts(): void
    {
        $tabs = Livewire::test(ListUsers::class)->instance()->getTabs();

        $this->assertSame('2', $tabs['staff']->getBadge());
        $this->assertSame('1', $tabs['students']->getBadge());
        $this->assertSame('1', $tabs['guardians']->getBadge());
    }

    public function test_grado_grupo_column_is_hidden_outside_the_estudiantes_tab(): void
    {
        Livewire::test(ListUsers::class)
            ->assertTableColumnHidden('grade_group');

        Livewire::test(ListUsers::class)
            ->set('activeTab', 'guardians')
            ->assertTableColumnHidden('grade_group');
    }

    public function test_grado_grupo_column_is_visible_with_correct_value_on_estudiantes_tab(): void
    {
        $institution = Institution::factory()->create();
        $cycle = Cycle::factory()->for($institution)->create();
        $schoolGrade = SchoolGrade::factory()->for($institution)->for($cycle)->create(['name' => '5° de Primaria']);
        $group = Group::factory()->for($cycle)->create(['name' => 'A']);

        $this->student->update(['school_grade_id' => $schoolGrade->id, 'group_id' => $group->id]);

        Livewire::test(ListUsers::class)
            ->set('activeTab', 'students')
            ->assertTableColumnVisible('grade_group')
            ->assertTableColumnStateSet('grade_group', '5° de Primaria - A', $this->student);
    }

    public function test_children_column_is_hidden_outside_the_acudientes_tab(): void
    {
        Livewire::test(ListUsers::class)
            ->assertTableColumnHidden('children.name');

        Livewire::test(ListUsers::class)
            ->set('activeTab', 'students')
            ->assertTableColumnHidden('children.name');
    }

    public function test_children_column_shows_the_linked_student_on_acudientes_tab(): void
    {
        $this->guardian->children()->attach($this->student->id, [
            'relationship' => 'madre',
            'is_primary_contact' => true,
        ]);

        Livewire::test(ListUsers::class)
            ->set('activeTab', 'guardians')
            ->assertTableColumnVisible('children.name')
            ->assertTableColumnStateSet('children.name', [$this->student->name], $this->guardian);
    }

    public function test_children_column_supports_a_guardian_linked_to_multiple_students(): void
    {
        $secondStudent = User::factory()->create()->assignRole('student');

        $this->guardian->children()->attach([
            $this->student->id => ['relationship' => 'madre', 'is_primary_contact' => true],
            $secondStudent->id => ['relationship' => 'madre', 'is_primary_contact' => false],
        ]);

        $response = Livewire::test(ListUsers::class)->set('activeTab', 'guardians');

        $column = $response->instance()->getTable()->getColumn('children.name');
        $record = $response->instance()->getTableRecord((string) $this->guardian->getKey());
        $column->record($record);
        $column->clearCachedState();

        $state = $column->getState();
        sort($state);

        $expectedNames = [$this->student->name, $secondStudent->name];
        sort($expectedNames);

        $this->assertSame($expectedNames, $state);
    }
}
