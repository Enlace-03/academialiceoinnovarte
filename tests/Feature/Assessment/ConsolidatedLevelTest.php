<?php

namespace Tests\Feature\Assessment;

use App\Modules\Assessment\Models\Evaluation;
use App\Modules\Assessment\Models\EvaluationResult;
use App\Modules\Assessment\Models\RubricCriterion;
use App\Modules\Assessment\Models\RubricLevel;
use Database\Seeders\RubricLevelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsolidatedLevelTest extends TestCase
{
    use RefreshDatabase;

    public function test_consolidated_level_is_the_mode_of_its_results(): void
    {
        $this->seed(RubricLevelSeeder::class);

        $evaluation = Evaluation::factory()->create();
        $enProceso = RubricLevel::where('key', 'en_proceso')->firstOrFail();
        $logroEsperado = RubricLevel::where('key', 'logro_esperado')->firstOrFail();

        EvaluationResult::factory()->for($evaluation)->create(['rubric_level_id' => $enProceso->id]);
        EvaluationResult::factory()->for($evaluation)->create(['rubric_level_id' => $enProceso->id]);
        EvaluationResult::factory()->for($evaluation)->create(['rubric_level_id' => $logroEsperado->id]);

        $this->assertTrue($evaluation->consolidatedLevel()->is($enProceso));
    }

    public function test_a_tie_is_resolved_by_the_lowest_level(): void
    {
        $this->seed(RubricLevelSeeder::class);

        $evaluation = Evaluation::factory()->create();
        $inicio = RubricLevel::where('key', 'inicio')->firstOrFail();
        $logroDestacado = RubricLevel::where('key', 'logro_destacado')->firstOrFail();

        EvaluationResult::factory()->for($evaluation)->create(['rubric_level_id' => $inicio->id]);
        EvaluationResult::factory()->for($evaluation)->create(['rubric_level_id' => $logroDestacado->id]);

        $this->assertTrue($evaluation->consolidatedLevel()->is($inicio));
    }

    public function test_returns_null_without_results(): void
    {
        $evaluation = Evaluation::factory()->create();

        $this->assertNull($evaluation->consolidatedLevel());
    }
}
