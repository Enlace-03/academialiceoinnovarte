<?php

namespace Tests\Feature\Identity;

use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Admin\Resources\Users\RelationManagers\GuardiansRelationManager;
use App\Models\User;
use App\Modules\Identity\Models\ParentStudent;
use App\Modules\Identity\Policies\StudentPolicy;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ParentStudentRelationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);

        $admin = User::factory()->create()->assignRole('super_admin');
        $this->actingAs($admin);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_children_and_guardians_relations_return_pivot_data(): void
    {
        $parent = User::factory()->create()->assignRole('parent');
        $student = User::factory()->create()->assignRole('student');

        $parent->children()->attach($student->id, [
            'relationship' => 'madre',
            'is_primary_contact' => true,
        ]);

        $child = $parent->children()->first();
        $this->assertTrue($child->is($student));
        $this->assertInstanceOf(ParentStudent::class, $child->pivot);
        $this->assertSame('madre', $child->pivot->relationship);
        $this->assertTrue($child->pivot->is_primary_contact);

        $guardian = $student->guardians()->first();
        $this->assertTrue($guardian->is($parent));
        $this->assertInstanceOf(ParentStudent::class, $guardian->pivot);
        $this->assertSame('madre', $guardian->pivot->relationship);
        $this->assertTrue($guardian->pivot->is_primary_contact);
    }

    public function test_student_policy_view_uses_the_children_relation_correctly(): void
    {
        $parent = User::factory()->create()->assignRole('parent');
        $otherParent = User::factory()->create()->assignRole('parent');
        $student = User::factory()->create()->assignRole('student');

        $parent->children()->attach($student->id, [
            'relationship' => 'padre',
            'is_primary_contact' => false,
        ]);

        $policy = app(StudentPolicy::class);

        $this->assertTrue($policy->view($parent, $student));
        $this->assertFalse($policy->view($otherParent, $student));
    }

    public function test_attach_action_only_offers_users_with_parent_role(): void
    {
        $realParent = User::factory()->create()->assignRole('parent');
        $teacher = User::factory()->create()->assignRole('teacher');
        $student = User::factory()->create()->assignRole('student');

        Livewire::test(GuardiansRelationManager::class, [
            'ownerRecord' => $student,
            'pageClass' => EditUser::class,
        ])
            ->mountTableAction('attach')
            ->assertFormFieldExists(
                'recordId',
                function (Select $field) use ($realParent, $teacher): bool {
                    $options = $field->getOptions();

                    return array_key_exists($realParent->id, $options)
                        && ! array_key_exists($teacher->id, $options);
                }
            );
    }

    public function test_self_reference_is_blocked(): void
    {
        $studentAndParent = User::factory()->create()->assignRole(['student', 'parent']);

        Livewire::test(GuardiansRelationManager::class, [
            'ownerRecord' => $studentAndParent,
            'pageClass' => EditUser::class,
        ])
            ->mountTableAction('attach')
            ->setTableActionData([
                'recordId' => $studentAndParent->id,
                'relationship' => 'tutor',
                'is_primary_contact' => false,
            ])
            ->callMountedTableAction()
            ->assertHasTableActionErrors(['recordId']);

        $this->assertDatabaseMissing('parent_student', [
            'parent_id' => $studentAndParent->id,
            'student_id' => $studentAndParent->id,
        ]);
    }

    public function test_editing_an_existing_relation_updates_pivot_fields(): void
    {
        $parent = User::factory()->create()->assignRole('parent');
        $student = User::factory()->create()->assignRole('student');

        $parent->children()->attach($student->id, [
            'relationship' => 'padre',
            'is_primary_contact' => false,
        ]);

        Livewire::test(GuardiansRelationManager::class, [
            'ownerRecord' => $student,
            'pageClass' => EditUser::class,
        ])
            ->mountTableAction('edit', record: $parent->getKey())
            ->setTableActionData([
                'relationship' => 'tutor',
                'is_primary_contact' => true,
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('parent_student', [
            'parent_id' => $parent->id,
            'student_id' => $student->id,
            'relationship' => 'tutor',
            'is_primary_contact' => true,
        ]);

        $this->assertDatabaseCount('parent_student', 1);
    }
}
