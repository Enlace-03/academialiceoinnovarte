<?php

namespace Tests\Feature\Identity;

use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Admin\Resources\Users\RelationManagers\GuardiansRelationManager;
use App\Models\User;
use App\Modules\Identity\Models\DataTreatmentConsent;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DataTreatmentConsentTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);

        $this->admin = User::factory()->create()->assignRole('super_admin');
        $this->actingAs($this->admin);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_attach_without_consent_checkbox_fails_and_creates_nothing(): void
    {
        $parent = User::factory()->create()->assignRole('parent');
        $student = User::factory()->create()->assignRole('student');

        Livewire::test(GuardiansRelationManager::class, [
            'ownerRecord' => $student,
            'pageClass' => EditUser::class,
        ])
            ->mountTableAction('attach')
            ->setTableActionData([
                'recordId' => $parent->id,
                'relationship' => 'padre',
                'is_primary_contact' => false,
                'data_treatment_consent' => false,
            ])
            ->callMountedTableAction()
            ->assertHasTableActionErrors(['data_treatment_consent']);

        $this->assertDatabaseMissing('parent_student', [
            'parent_id' => $parent->id,
            'student_id' => $student->id,
        ]);

        $this->assertDatabaseCount('data_treatment_consents', 0);
    }

    public function test_attach_with_consent_checkbox_creates_relation_and_consent_record(): void
    {
        $parent = User::factory()->create()->assignRole('parent');
        $student = User::factory()->create()->assignRole('student');

        Livewire::test(GuardiansRelationManager::class, [
            'ownerRecord' => $student,
            'pageClass' => EditUser::class,
        ])
            ->mountTableAction('attach')
            ->setTableActionData([
                'recordId' => $parent->id,
                'relationship' => 'padre',
                'is_primary_contact' => true,
                'data_treatment_consent' => true,
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('parent_student', [
            'parent_id' => $parent->id,
            'student_id' => $student->id,
            'relationship' => 'padre',
            'is_primary_contact' => true,
        ]);

        $this->assertDatabaseCount('data_treatment_consents', 1);

        $consent = DataTreatmentConsent::firstOrFail();

        $this->assertTrue($consent->parent->is($parent));
        $this->assertTrue($consent->student->is($student));
        $this->assertTrue($consent->confirmedBy->is($this->admin));
        $this->assertSame('admin_confirmed', $consent->method);
        $this->assertSame(config('legal.data_treatment_policy_version'), $consent->policy_version);
        $this->assertNotNull($consent->accepted_at);
    }

    public function test_unique_constraint_prevents_duplicate_consent_for_same_pair_and_version(): void
    {
        $parent = User::factory()->create()->assignRole('parent');
        $student = User::factory()->create()->assignRole('student');

        DataTreatmentConsent::create([
            'parent_id' => $parent->id,
            'student_id' => $student->id,
            'policy_version' => config('legal.data_treatment_policy_version'),
            'method' => 'admin_confirmed',
            'confirmed_by_user_id' => $this->admin->id,
            'accepted_at' => now(),
        ]);

        try {
            DataTreatmentConsent::create([
                'parent_id' => $parent->id,
                'student_id' => $student->id,
                'policy_version' => config('legal.data_treatment_policy_version'),
                'method' => 'admin_confirmed',
                'confirmed_by_user_id' => $this->admin->id,
                'accepted_at' => now(),
            ]);

            $this->fail('Expected a unique constraint violation for a duplicate (parent_id, student_id, policy_version).');
        } catch (QueryException) {
            // esperado — el unique constraint hizo su trabajo.
        }

        $this->assertDatabaseCount('data_treatment_consents', 1);
    }
}
