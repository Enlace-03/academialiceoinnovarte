<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Models;

use Database\Factories\EvaluationAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Documento de retroalimentación del docente (la devolución), no evidencia
 * del estudiante: vive ligado a Evaluation, nunca a Submission, para que no
 * pueda aparecer mezclado con SubmissionAttachment. Solo documentos
 * (PDF/docx/xlsx/pptx), por eso no hay columna type.
 */
#[Fillable(['evaluation_id', 'file_disk', 'file_path', 'original_filename'])]
class EvaluationAttachment extends Model
{
    use HasFactory, HasUuids;

    protected static function newFactory(): EvaluationAttachmentFactory
    {
        return EvaluationAttachmentFactory::new();
    }

    protected static function booted(): void
    {
        static::deleting(function (self $attachment): void {
            Storage::disk($attachment->file_disk)->delete($attachment->file_path);
        });
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }
}
