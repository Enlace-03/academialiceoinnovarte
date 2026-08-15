<?php

declare(strict_types=1);

namespace App\Modules\Community\Models;

use App\Models\User;
use Database\Factories\PrivateChatMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mismas tres columnas de moderación que forum_threads/forum_posts/
 * chat_messages (is_hidden/hidden_at/hidden_by_user_id) a propósito --
 * HideCommunityContentAction se extiende para aceptar también este modelo,
 * en vez de duplicar la Action.
 */
#[Fillable(['thread_id', 'user_id', 'content', 'is_hidden', 'hidden_at', 'hidden_by_user_id'])]
class PrivateChatMessage extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'is_hidden' => 'boolean',
            'hidden_at' => 'datetime',
        ];
    }

    protected static function newFactory(): PrivateChatMessageFactory
    {
        return PrivateChatMessageFactory::new();
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(PrivateChatThread::class, 'thread_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hiddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hidden_by_user_id');
    }
}
