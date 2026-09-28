<?php

namespace Tests\Feature\Assessment;

use App\Models\User;
use App\Modules\Assessment\Models\ReportCard;
use Database\Seeders\RoleLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * ReportCardPolicy::view() y el permiso report_cards.view. Los roles salen
 * del preset REAL de config/permissions.php (RolePermissionSeeder), no de
 * permisos asignados a mano. Ningún usuario aquí es super_admin
 * (Gate::before) para ejercitar la policy de verdad.
 */
class ReportCardPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLevelSeeder::class);

        $this->student = User::factory()->create()->assignRole('student');
    }

    private function can(User $user): bool
    {
        return Gate::forUser($user)->allows('view', [ReportCard::class, $this->student]);
    }

    public static function rolesWithThePermissionProvider(): array
    {
        return [
            'rector' => ['rector'],
            'coordinator' => ['coordinator'],
            'teacher' => ['teacher'],
        ];
    }

    #[DataProvider('rolesWithThePermissionProvider')]
    public function test_the_preset_of_rector_coordinator_and_teacher_grants_report_cards_view(string $role): void
    {
        $user = User::factory()->create()->assignRole($role);

        $this->assertTrue($user->hasPermissionTo('report_cards.view'));
        $this->assertTrue($this->can($user));
    }

    public function test_secretary_does_not_have_the_permission_and_cannot_view(): void
    {
        $secretary = User::factory()->create()->assignRole('secretary');

        $this->assertFalse($secretary->hasPermissionTo('report_cards.view'));
        $this->assertFalse($this->can($secretary));
    }

    public function test_the_guardian_of_the_student_can_view(): void
    {
        $guardian = User::factory()->create()->assignRole('parent');
        $guardian->children()->attach($this->student->id, ['relationship' => 'madre']);

        $this->assertTrue($this->can($guardian));
    }

    public function test_a_guardian_of_another_student_cannot_view(): void
    {
        $otherStudent = User::factory()->create()->assignRole('student');
        $guardian = User::factory()->create()->assignRole('parent');
        $guardian->children()->attach($otherStudent->id, ['relationship' => 'madre']);

        $this->assertFalse($this->can($guardian));
    }

    public function test_the_student_themselves_cannot_view_their_own_report_card(): void
    {
        $this->assertFalse($this->can($this->student));
    }

    public function test_another_student_cannot_view(): void
    {
        $other = User::factory()->create()->assignRole('student');

        $this->assertFalse($this->can($other));
    }

    public function test_authorize_throws_for_a_denied_user(): void
    {
        $this->expectException(AuthorizationException::class);

        Gate::forUser($this->student)->authorize('view', [ReportCard::class, $this->student]);
    }
}
