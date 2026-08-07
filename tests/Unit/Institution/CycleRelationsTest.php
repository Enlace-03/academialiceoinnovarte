<?php

namespace Tests\Unit\Institution;

use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\Group;
use App\Modules\Institution\Models\SchoolGrade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CycleRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cycle_has_many_school_grades(): void
    {
        $cycle = Cycle::factory()->create();
        $gradeA = SchoolGrade::factory()->for($cycle)->create();
        $gradeB = SchoolGrade::factory()->for($cycle)->create();

        $this->assertTrue($cycle->schoolGrades->contains($gradeA));
        $this->assertTrue($cycle->schoolGrades->contains($gradeB));
        $this->assertCount(2, $cycle->schoolGrades);
    }

    public function test_school_grade_belongs_to_cycle(): void
    {
        $cycle = Cycle::factory()->create();
        $grade = SchoolGrade::factory()->for($cycle)->create();

        $this->assertTrue($grade->cycle->is($cycle));
    }

    public function test_school_grade_can_exist_without_a_cycle(): void
    {
        $grade = SchoolGrade::factory()->create(['cycle_id' => null]);

        $this->assertNull($grade->cycle);
    }

    public function test_cycle_has_many_groups(): void
    {
        $cycle = Cycle::factory()->create();
        $groupA = Group::factory()->for($cycle)->create(['name' => 'A']);
        $groupB = Group::factory()->for($cycle)->create(['name' => 'B']);

        $this->assertTrue($cycle->groups->contains($groupA));
        $this->assertTrue($cycle->groups->contains($groupB));
        $this->assertCount(2, $cycle->groups);
    }

    public function test_group_belongs_to_cycle(): void
    {
        $cycle = Cycle::factory()->create();
        $group = Group::factory()->for($cycle)->create();

        $this->assertTrue($group->cycle->is($cycle));
    }
}
