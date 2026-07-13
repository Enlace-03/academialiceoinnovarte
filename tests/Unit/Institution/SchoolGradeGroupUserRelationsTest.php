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

    public function test_school_grade_has_many_groups(): void
    {
        $schoolGrade = SchoolGrade::factory()->create();
        $groupA = Group::factory()->for($schoolGrade)->create(['name' => 'A']);
        $groupB = Group::factory()->for($schoolGrade)->create(['name' => 'B']);

        $this->assertTrue($schoolGrade->groups->contains($groupA));
        $this->assertTrue($schoolGrade->groups->contains($groupB));
        $this->assertCount(2, $schoolGrade->groups);
    }

    public function test_group_belongs_to_school_grade(): void
    {
        $schoolGrade = SchoolGrade::factory()->create();
        $group = Group::factory()->for($schoolGrade)->create();

        $this->assertTrue($group->schoolGrade->is($schoolGrade));
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

    public function test_user_school_grade_accessor_derives_from_group(): void
    {
        $schoolGrade = SchoolGrade::factory()->create();
        $group = Group::factory()->for($schoolGrade)->create();
        $student = User::factory()->create(['group_id' => $group->id])->assignRole('student');

        $this->assertTrue($student->schoolGrade()->is($schoolGrade));
    }

    public function test_user_without_group_has_null_school_grade(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->schoolGrade());
    }
}
