<?php

namespace Tests\Feature\Institution;

use App\Filament\Admin\Resources\Cycles\Pages\CreateCycle;
use App\Filament\Admin\Resources\Cycles\Pages\EditCycle;
use App\Filament\Admin\Resources\Cycles\Pages\ListCycles;
use App\Filament\Admin\Resources\Groups\Pages\CreateGroup;
use App\Filament\Admin\Resources\Groups\Pages\EditGroup;
use App\Filament\Admin\Resources\Groups\Pages\ListGroups;
use App\Filament\Admin\Resources\SchoolGrades\Pages\CreateSchoolGrade;
use App\Filament\Admin\Resources\SchoolGrades\Pages\EditSchoolGrade;
use App\Filament\Admin\Resources\SchoolGrades\Pages\ListSchoolGrades;
use App\Filament\Admin\Resources\ThinkingFields\Pages\CreateThinkingField;
use App\Filament\Admin\Resources\ThinkingFields\Pages\EditThinkingField;
use App\Filament\Admin\Resources\ThinkingFields\Pages\ListThinkingFields;
use App\Models\User;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\Group;
use App\Modules\Institution\Models\Institution;
use App\Modules\Institution\Models\SchoolGrade;
use App\Modules\Institution\Models\ThinkingField;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InstitutionResourcesCrudTest extends TestCase
{
    use RefreshDatabase;

    protected Institution $institution;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);

        $rector = User::factory()->create()->assignRole('rector');
        $this->actingAs($rector);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->institution = Institution::factory()->create();
    }

    // --- Cycle ---------------------------------------------------------

    public function test_cycles_list_loads(): void
    {
        Cycle::factory()->for($this->institution)->create();

        Livewire::test(ListCycles::class)->assertOk();
    }

    public function test_cycle_can_be_created(): void
    {
        Livewire::test(CreateCycle::class)
            ->fillForm([
                'name' => 'Exploratorio',
                'order' => 1,
                'description' => 'Ciclo de prueba',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('cycles', ['name' => 'Exploratorio', 'order' => 1]);
    }

    public function test_cycle_can_be_edited(): void
    {
        $cycle = Cycle::factory()->for($this->institution)->create(['name' => 'Original']);

        Livewire::test(EditCycle::class, ['record' => $cycle->getRouteKey()])
            ->fillForm(['name' => 'Renombrado'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('cycles', ['id' => $cycle->id, 'name' => 'Renombrado']);
    }

    // --- ThinkingField ---------------------------------------------------

    public function test_thinking_fields_list_loads(): void
    {
        ThinkingField::factory()->for($this->institution)->create();

        Livewire::test(ListThinkingFields::class)->assertOk();
    }

    public function test_thinking_field_can_be_created(): void
    {
        Livewire::test(CreateThinkingField::class)
            ->fillForm([
                'name' => 'Lenguaje y Comunicación',
                'slug' => 'lenguaje-y-comunicacion',
                'purpose' => 'Propósito de prueba',
                'order' => 1,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('thinking_fields', ['slug' => 'lenguaje-y-comunicacion']);
    }

    public function test_thinking_field_can_be_edited(): void
    {
        $field = ThinkingField::factory()->for($this->institution)->create(['name' => 'Original']);

        Livewire::test(EditThinkingField::class, ['record' => $field->getRouteKey()])
            ->fillForm(['name' => 'Renombrado'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('thinking_fields', ['id' => $field->id, 'name' => 'Renombrado']);
    }

    // --- SchoolGrade -------------------------------------------------------

    public function test_school_grades_list_loads(): void
    {
        SchoolGrade::factory()->for($this->institution)->create();

        Livewire::test(ListSchoolGrades::class)->assertOk();
    }

    public function test_school_grade_can_be_created(): void
    {
        $cycle = Cycle::factory()->for($this->institution)->create();

        Livewire::test(CreateSchoolGrade::class)
            ->fillForm([
                'name' => '3°',
                'level' => 3,
                'cycle_id' => $cycle->id,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('school_grades', ['name' => '3°', 'level' => 3, 'cycle_id' => $cycle->id]);
    }

    public function test_school_grade_can_be_edited_and_toggled_inactive(): void
    {
        $cycle = Cycle::factory()->for($this->institution)->create();
        $grade = SchoolGrade::factory()->for($this->institution)->create(['is_active' => true]);

        Livewire::test(EditSchoolGrade::class, ['record' => $grade->getRouteKey()])
            ->fillForm(['cycle_id' => $cycle->id, 'is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('school_grades', ['id' => $grade->id, 'cycle_id' => $cycle->id, 'is_active' => false]);
    }

    public function test_school_grade_edit_page_has_no_delete_action(): void
    {
        $grade = SchoolGrade::factory()->for($this->institution)->create();

        Livewire::test(EditSchoolGrade::class, ['record' => $grade->getRouteKey()])
            ->assertActionDoesNotExist('delete');
    }

    // --- Group ---------------------------------------------------------

    public function test_groups_list_loads(): void
    {
        $grade = SchoolGrade::factory()->for($this->institution)->create();
        Group::factory()->for($grade)->create();

        Livewire::test(ListGroups::class)->assertOk();
    }

    public function test_group_can_be_created(): void
    {
        $grade = SchoolGrade::factory()->for($this->institution)->create(['is_active' => true]);

        Livewire::test(CreateGroup::class)
            ->fillForm([
                'school_grade_id' => $grade->id,
                'name' => 'C',
                'year' => (int) config('school.current_academic_year'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('groups', ['school_grade_id' => $grade->id, 'name' => 'C']);
    }

    public function test_group_can_be_edited(): void
    {
        $grade = SchoolGrade::factory()->for($this->institution)->create(['is_active' => true]);
        $group = Group::factory()->for($grade)->create(['name' => 'A']);

        Livewire::test(EditGroup::class, ['record' => $group->getRouteKey()])
            ->fillForm(['name' => 'B'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('groups', ['id' => $group->id, 'name' => 'B']);
    }
}
