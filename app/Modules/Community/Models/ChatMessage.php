<?php

declare(strict_types=1);

namespace App\Modules\Community\Models;

use App\Models\User;
use App\Modules\Institution\Models\Group;
use Database\Factories\ChatMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sin project_id a propósito: el chat es del grupo, no de un proyecto puntual
 * (decisión ya confirmada). Sin softDeletes: a diferencia del foro, el chat
 * no tiene borrado propio del autor, solo ocultar (moderación).
 */
#[Fillable(['group_id', 'user_id', 'content', 'is_hidden', 'hidden_at', 'hidden_by_user_id'])]
class ChatMessage extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_hidden' => 'boolean',
            'hidden_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ChatMessageFactory
    {
        return ChatMessageFactory::new();
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
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
