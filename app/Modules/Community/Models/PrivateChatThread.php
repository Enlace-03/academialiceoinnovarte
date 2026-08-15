<?php

declare(strict_types=1);

namespace App\Modules\Community\Models;

use App\Models\User;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTeam;
use Database\Factories\PrivateChatThreadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * student_id/team_id son mutuamente excluyentes según 'type' -- ver
 * docblock de la migración. No hay Enum de PHP para 'type' a propósito
 * (mismo criterio que Submission::STATUSES): dos valores fijos, sin
 * necesidad de la maquinaria de un backed enum.
 */
#[Fillable(['project_id', 'type', 'student_id', 'team_id'])]
class PrivateChatThread extends Model
{
    use HasFactory, HasUuids;

    public const TYPES = ['individual', 'team'];

    protected static function newFactory(): PrivateChatThreadFactory
    {
        return PrivateChatThreadFactory::new();
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(ProjectTeam::class, 'team_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(PrivateChatMessage::class, 'thread_id');
    }
}
