<?php

namespace Tests\Feature\Project;

use App\Modules\Project\Models\ExpectedEvidence;
use App\Modules\Project\Models\Phase;
use App\Modules\Project\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectPhaseGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_project_generates_the_four_institutional_phases_in_order(): void
    {
        $project = Project::factory()->create();

        $phases = $project->phases()->orderBy('order')->get();

        $this->assertCount(4, $phases);
        $this->assertSame(Phase::INSTITUTIONAL_NAMES, $phases->pluck('name', 'order')->toArray());
    }

    public function test_expected_evidences_sharing_alternative_group_are_mutually_exclusive_alternatives(): void
    {
        $phase = Phase::factory()->create();

        $video = ExpectedEvidence::factory()->for($phase)->create(['alternative_group' => 'producto_final']);
        $podcast = ExpectedEvidence::factory()->for($phase)->create(['alternative_group' => 'producto_final']);
        $independent = ExpectedEvidence::factory()->for($phase)->create(['alternative_group' => null]);

        $this->assertTrue($video->alternatives()->contains($podcast));
        $this->assertFalse($video->alternatives()->contains($independent));
        $this->assertTrue($independent->alternatives()->isEmpty());
    }
}
