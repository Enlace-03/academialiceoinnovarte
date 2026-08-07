<?php

namespace Tests\Feature\Institution;

use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\Group;
use App\Modules\Institution\Models\SchoolGrade;
use Database\Seeders\InstitutionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InstitutionSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(InstitutionSeeder::class);
    }

    public function test_seeds_grades_zero_to_nine(): void
    {
        $levels = SchoolGrade::query()->orderBy('level')->pluck('level')->all();

        $this->assertSame(range(0, 9), $levels);
    }

    public function test_seeds_four_cycles_in_order(): void
    {
        $orders = \App\Modules\Institution\Models\Cycle::query()->orderBy('order')->pluck('order')->all();

        $this->assertSame([1, 2, 3, 4], $orders);
    }

    public function test_seeds_four_thinking_fields_in_order(): void
    {
        $orders = \App\Modules\Institution\Models\ThinkingField::query()->orderBy('order')->pluck('order')->all();

        $this->assertSame([1, 2, 3, 4], $orders);
    }

    #[DataProvider('gradeCycleProvider')]
    public function test_each_grade_is_assigned_its_correct_cycle(int $level, int $expectedCycleOrder): void
    {
        $grade = SchoolGrade::where('level', $level)->firstOrFail();

        $this->assertNotNull($grade->cycle_id);
        $this->assertSame($expectedCycleOrder, $grade->cycle->order);
    }

    public static function gradeCycleProvider(): array
    {
        return [
            'grado 0 -> ciclo 1 (Exploratorio)' => [0, 1],
            'grado 1 -> ciclo 1 (Exploratorio)' => [1, 1],
            'grado 2 -> ciclo 1 (Exploratorio)' => [2, 1],
            'grado 3 -> ciclo 2 (Conceptual)' => [3, 2],
            'grado 4 -> ciclo 2 (Conceptual)' => [4, 2],
            'grado 5 -> ciclo 2 (Conceptual)' => [5, 2],
            'grado 6 -> ciclo 3 (Contextual)' => [6, 3],
            'grado 7 -> ciclo 3 (Contextual)' => [7, 3],
            'grado 8 -> ciclo 4 (Proyectivo)' => [8, 4],
            'grado 9 -> ciclo 4 (Proyectivo)' => [9, 4],
        ];
    }

    public function test_all_seeded_grades_are_active(): void
    {
        $this->assertSame(10, SchoolGrade::where('is_active', true)->count());
    }

    public function test_seeds_two_groups_per_cycle(): void
    {
        $this->assertSame(8, Group::count());

        Cycle::all()->each(function (Cycle $cycle) {
            $this->assertSame(2, Group::where('cycle_id', $cycle->id)->count());
        });
    }

    public function test_group_names_combine_cycle_name_and_section(): void
    {
        $exploratorio = Cycle::where('order', 1)->firstOrFail();

        $names = Group::where('cycle_id', $exploratorio->id)->orderBy('name')->pluck('name')->all();

        $this->assertSame(['Exploratorio - A', 'Exploratorio - B'], $names);
    }
}
