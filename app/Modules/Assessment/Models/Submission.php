<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Models;

use App\Models\User;
use App\Modules\Community\Models\ForumPost;
use App\Modules\Project\Models\ExpectedEvidence;
use Database\Factories\SubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Sin historial de versiones (decisión confirmada del Hito 2): al corregir
 * una entrega devuelta se actualiza esta misma fila —
 * unique(expected_evidence_id, student_id) ya lo garantiza.
 *
 * Adjuntos (foto/enlace) viven en SubmissionAttachment desde el Hito 3b-3
 * (antes: columnas escalares file_disk/file_path/original_filename, un solo
 * archivo por entrega -- ver migración 2027_01_01_000400 para la conversión).
 */
#[Fillable([
    'expected_evidence_id', 'student_id', 'text_content', 'status', 'submitted_at',
    'forum_post_id',
])]
class Submission extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const STATUSES = [
        'submitted' => 'Entregado',
        'evaluated' => 'Evaluado',
        'returned' => 'Devuelto',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    protected static function newFactory(): SubmissionFactory
    {
        return SubmissionFactory::new();
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function expectedEvidence(): BelongsTo
    {
        return $this->belongsTo(ExpectedEvidence::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    public function forumPost(): BelongsTo
    {
        return $this->belongsTo(ForumPost::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SubmissionAttachment::class)->orderBy('order');
    }
}
