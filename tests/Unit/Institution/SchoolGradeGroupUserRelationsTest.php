<?php

namespace Tests\Unit\Institution;

use App\Models\User;
use App\Modules\Institution\Models\Group;
use App\Modules\Institution\Models\SchoolGrade;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolGradeGroupUserRelationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_group_has_many_users(): void
    {
        $group = Group::factory()->create();
        $student = User::factory()->create(['group_id' => $group->id])->assignRole('student');

        $this->assertTrue($group->users->contains($student));
    }

    public function test_user_belongs_to_group(): void
    {
        $group = Group::factory()->create();
        $student = User::factory()->create(['group_id' => $group->id])->assignRole('student');

        $this->assertTrue($student->group->is($group));
    }

    public function test_school_grade_has_many_users(): void
    {
        $schoolGrade = SchoolGrade::factory()->create();
        $studentA = User::factory()->create(['school_grade_id' => $schoolGrade->id])->assignRole('student');
        $studentB = User::factory()->create(['school_grade_id' => $schoolGrade->id])->assignRole('student');

        $this->assertTrue($schoolGrade->users->contains($studentA));
        $this->assertTrue($schoolGrade->users->contains($studentB));
        $this->assertCount(2, $schoolGrade->users);
    }

    public function test_user_belongs_to_school_grade_directly(): void
    {
        $schoolGrade = SchoolGrade::factory()->create();
        $student = User::factory()->create(['school_grade_id' => $schoolGrade->id])->assignRole('student');

        $this->assertTrue($student->schoolGrade->is($schoolGrade));
    }

    public function test_user_school_grade_does_not_depend_on_group(): void
    {
        // El grado ya no se deriva del grupo: un estudiante puede tener
        // grado propio sin tener grupo asignado todavía.
        $schoolGrade = SchoolGrade::factory()->create();
        $student = User::factory()->create([
            'school_grade_id' => $schoolGrade->id,
            'group_id' => null,
        ])->assignRole('student');

        $this->assertTrue($student->schoolGrade->is($schoolGrade));
    }

    public function test_user_without_school_grade_has_null_school_grade(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->schoolGrade);
    }
}
