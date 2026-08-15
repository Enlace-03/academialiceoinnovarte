<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Models\User;
use Database\Factories\StudentPhotoModerationLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Auditoría de intervenciones de personal sobre la foto de perfil de un
 * estudiante -- ver la migración para por qué solo created_at.
 */
#[Fillable(['student_id', 'action', 'performed_by_user_id'])]
class StudentPhotoModerationLog extends Model
{
    use HasFactory;

    protected $table = 'student_photo_moderation_log';

    const UPDATED_AT = null;

    public const ACTIONS = [
        'removed' => 'Foto eliminada',
        'blocked' => 'Subida bloqueada',
        'unblocked' => 'Subida desbloqueada',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    protected static function newFactory(): StudentPhotoModerationLogFactory
    {
        return StudentPhotoModerationLogFactory::new();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }
}
