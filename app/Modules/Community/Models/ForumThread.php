<?php

declare(strict_types=1);

namespace App\Modules\Community\Models;

use App\Models\User;
use App\Modules\Project\Models\Phase;
use App\Modules\Project\Models\Project;
use Database\Factories\ForumThreadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * phase_id es nullable a propósito: un hilo puede ser general del proyecto
 * (sin fase) o específico de una fase — decisión ya confirmada, no un dato
 * incompleto.
 */
#[Fillable(['project_id', 'phase_id', 'created_by', 'title', 'is_hidden', 'hidden_at', 'hidden_by_user_id'])]
class ForumThread extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_hidden' => 'boolean',
            'hidden_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ForumThreadFactory
    {
        return ForumThreadFactory::new();
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function hiddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hidden_by_user_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(ForumPost::class);
    }
}
