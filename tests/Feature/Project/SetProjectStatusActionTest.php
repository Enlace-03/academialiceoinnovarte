<?php

namespace Tests\Feature\Project;

use App\Modules\Project\Actions\SetProjectStatusAction;
use App\Modules\Project\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetProjectStatusActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishes_a_draft_project(): void
    {
        $project = Project::factory()->draft()->create();

        $result = app(SetProjectStatusAction::class)->execute($project, 'published');

        $this->assertSame('published', $result->status);
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'status' => 'published']);
    }

    public function test_returns_a_published_project_to_draft(): void
    {
        $project = Project::factory()->create();

        app(SetProjectStatusAction::class)->execute($project, 'draft');

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'status' => 'draft']);
    }
}
