<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Models;

use App\Models\User;
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
 * file_disk/file_path/original_filename: mecanismo de archivo pragmático
 * (MediaLibrary confirmado sin conectar en ningún modelo del proyecto),
 * mismo patrón que Resource::url_or_path del Hito 1C.
 */
#[Fillable([
    'expected_evidence_id', 'student_id', 'text_content', 'status', 'submitted_at',
    'file_disk', 'file_path', 'original_filename',
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
}
