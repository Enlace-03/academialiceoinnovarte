<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * User::isGuardianOf() extraído de la duplicación real encontrada en
 * UserPolicy (viewPhoto()/isEligibleGuardianForPhoto()) y en
 * PortalHome (uploadPhoto()/removePhoto()) -- único punto de verdad
 * reutilizado por Policies y por la verificación explícita a mano en cada
 * componente del drill-down del acudiente.
 */
class UserIsGuardianOfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guardian_of_the_student_returns_true(): void
    {
        $guardian = User::factory()->create()->assignRole('parent');
        $student = User::factory()->create()->assignRole('student');
        $guardian->children()->attach($student->id, ['relationship' => 'madre']);

        $this->assertTrue($guardian->isGuardianOf($student));
    }

    public function test_unrelated_user_returns_false(): void
    {
        $guardian = User::factory()->create()->assignRole('parent');
        $unrelatedStudent = User::factory()->create()->assignRole('student');

        $this->assertFalse($guardian->isGuardianOf($unrelatedStudent));
    }

    public function test_guardian_of_a_different_student_does_not_leak_into_another_students_check(): void
    {
        $guardian = User::factory()->create()->assignRole('parent');
        $ownChild = User::factory()->create()->assignRole('student');
        $otherFamilysChild = User::factory()->create()->assignRole('student');
        $guardian->children()->attach($ownChild->id, ['relationship' => 'padre']);

        $this->assertTrue($guardian->isGuardianOf($ownChild));
        $this->assertFalse($guardian->isGuardianOf($otherFamilysChild));
    }
}
