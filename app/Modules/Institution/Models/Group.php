<?php

declare(strict_types=1);

namespace App\Modules\Institution\Models;

use App\Models\User;
use App\Modules\Community\Models\ChatMessage;
use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['cycle_id', 'name', 'year'])]
class Group extends Model
{
    use HasFactory, HasUuids;

    protected static function newFactory(): GroupFactory
    {
        return GroupFactory::new();
    }

    /**
     * The 'uuid' column is a secondary unique identifier, not the primary
     * key (which stays the native bigint 'id'). Without this override,
     * HasUuids defaults to treating the key name ('id') as the uuid column,
     * breaking auto-increment — same reasoning as User::uniqueIds().
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'group_id');
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }
}
