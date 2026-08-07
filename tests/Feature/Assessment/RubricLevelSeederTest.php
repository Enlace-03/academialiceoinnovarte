<?php

namespace Tests\Feature\Assessment;

use App\Modules\Assessment\Models\RubricLevel;
use Database\Seeders\RubricLevelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RubricLevelSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_the_four_confirmed_levels_in_order(): void
    {
        $this->seed(RubricLevelSeeder::class);

        $levels = RubricLevel::orderBy('order')->get();

        $this->assertCount(4, $levels);
        $this->assertSame(
            ['inicio', 'en_proceso', 'logro_esperado', 'logro_destacado'],
            $levels->pluck('key')->all(),
        );
        $this->assertSame(
            ['Inicio', 'En proceso', 'Logro esperado', 'Logro destacado'],
            $levels->pluck('label')->all(),
        );
        $this->assertSame([1, 2, 3, 4], $levels->pluck('order')->all());
    }

    public function test_seeding_twice_does_not_duplicate_levels(): void
    {
        $this->seed(RubricLevelSeeder::class);
        $this->seed(RubricLevelSeeder::class);

        $this->assertSame(4, RubricLevel::count());
    }
}
