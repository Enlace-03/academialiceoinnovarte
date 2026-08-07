<?php

declare(strict_types=1);

namespace App\Modules\Project\Models;

use Database\Factories\GuideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['phase_id', 'title', 'content'])]
class Guide extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected static function newFactory(): GuideFactory
    {
        return GuideFactory::new();
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }

    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class);
    }
}
