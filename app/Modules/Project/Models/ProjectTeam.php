<?php

declare(strict_types=1);

namespace App\Modules\Project\Models;

use App\Models\User;
use App\Modules\Institution\Models\Group;
use Database\Factories\ProjectTeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Catálogo de roles de equipo en config('project.team_roles'), editable sin
 * migración — mismo patrón que los presets de config/permissions.php.
 */
#[Fillable(['project_id', 'group_id', 'name'])]
class ProjectTeam extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): ProjectTeamFactory
    {
        return ProjectTeamFactory::new();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_team_user')
            ->withPivot('role_in_team')
            ->withTimestamps();
    }
}
