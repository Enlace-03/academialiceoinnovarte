<?php

declare(strict_types=1);

namespace App\Modules\Community\Actions;

use App\Models\User;
use App\Modules\Community\Models\ForumThread;
use App\Modules\Project\Models\Project;

final class CreateForumThreadAction
{
    /**
     * @param  array{title: string, phase_id?: int|null}  $data
     */
    public function execute(Project $project, User $creator, array $data): ForumThread
    {
        return ForumThread::create([
            'project_id' => $project->id,
            'phase_id' => $data['phase_id'] ?? null,
            'created_by' => $creator->id,
            'title' => $data['title'],
        ]);
    }
}
