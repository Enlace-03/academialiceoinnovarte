<?php

declare(strict_types=1);

namespace App\Modules\Community\Models;

use App\Models\User;
use Database\Factories\ForumPostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Respuestas de un solo nivel: parent_post_id apunta siempre a un post raíz
 * (parent_post_id NULL en ese raíz). La validación de que no se anide más de
 * un nivel vive en CreateForumPostAction, no aquí ni en la base de datos.
 */
#[Fillable(['forum_thread_id', 'parent_post_id', 'user_id', 'content', 'is_hidden', 'hidden_at', 'hidden_by_user_id'])]
class ForumPost extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_hidden' => 'boolean',
            'hidden_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ForumPostFactory
    {
        return ForumPostFactory::new();
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ForumThread::class, 'forum_thread_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parentPost(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_post_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_post_id');
    }

    public function hiddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hidden_by_user_id');
    }

    /**
     * Quién dio like: solo visible para personal (ver ForumPostPolicy).
     * El conteo público usa likes()->count(), no requiere cargar esta lista.
     */
    public function likes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'forum_post_likes')->withTimestamps();
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ForumPostPhoto::class)->orderBy('order');
    }
}
