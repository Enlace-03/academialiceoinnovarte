<?php

namespace Tests\Feature\Institution;

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
use Tests\TestCase;

class SchoolGradeActiveSelectorTest extends TestCase
{
    use RefreshDatabase;

    protected Institution $institution;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);

        $admin = User::factory()->create()->assignRole('super_admin');
        $this->actingAs($admin);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->institution = Institution::factory()->create();
    }

    public function test_inactive_grade_does_not_appear_in_the_enrollment_selector(): void
    {
        $activeGrade = SchoolGrade::factory()->for($this->institution)->create(['is_active' => true]);
        $inactiveGrade = SchoolGrade::factory()->for($this->institution)->create(['is_active' => false]);

        Livewire::test(CreateUser::class)
            ->assertFormFieldExists(
                'school_grade_id',
                function (Select $field) use ($activeGrade, $inactiveGrade): bool {
                    $options = $field->getOptions();

                    return array_key_exists($activeGrade->id, $options)
                        && ! array_key_exists($inactiveGrade->id, $options);
                }
            );
    }

    public function test_a_student_already_enrolled_in_a_now_inactive_grade_keeps_it_selectable_and_intact(): void
    {
        $cycle = Cycle::factory()->for($this->institution)->create();
        $inactiveGrade = SchoolGrade::factory()->for($this->institution)->for($cycle)->create(['is_active' => true]);
        $group = Group::factory()->for($cycle)->create([
            'year' => config('school.current_academic_year'),
        ]);

        $student = User::factory()->create([
            'school_grade_id' => $inactiveGrade->id,
            'group_id' => $group->id,
        ]);
        $student->assignRole('student');

        // Se desactiva el grado DESPUÉS de matricular al estudiante — simula el
        // caso real: coordinador quita el grado de los selectores a mitad de año.
        $inactiveGrade->update(['is_active' => false]);

        Livewire::test(EditUser::class, ['record' => $student->getRouteKey()])
            ->assertFormSet(['school_grade_id' => $inactiveGrade->id])
            ->assertFormFieldExists(
                'school_grade_id',
                fn (Select $field): bool => array_key_exists($inactiveGrade->id, $field->getOptions())
            )
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'school_grade_id' => $inactiveGrade->id,
            'group_id' => $group->id,
        ]);
    }
}
