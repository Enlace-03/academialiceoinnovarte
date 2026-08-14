<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Models;

use App\Modules\Shared\Actions\CompressUploadedImageAction;
use Database\Factories\SubmissionAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * type: photo | link. A diferencia de GalleryPhoto/ForumPostPhoto (siempre
 * foto), aquí no todo registro tiene archivo -- no puede reusar el trait
 * CompressesPhotoUploads tal cual (asume file_path siempre presente). La
 * misma lógica de compresión/limpieza vive acá, condicionada a
 * type === 'photo'.
 */
#[Fillable([
    'submission_id', 'type', 'file_disk', 'file_path', 'original_filename',
    'url', 'is_youtube', 'order',
])]
class SubmissionAttachment extends Model
{
    use HasFactory, HasUuids;

    public const TYPES = ['photo', 'link'];

    protected $casts = [
        'is_youtube' => 'boolean',
    ];

    protected static function newFactory(): SubmissionAttachmentFactory
    {
        return SubmissionAttachmentFactory::new();
    }

    protected static function booted(): void
    {
        static::created(function (self $attachment): void {
            if ($attachment->type !== 'photo') {
                return;
            }

            $directory = dirname($attachment->file_path);
            $targetPath = ($directory === '.' ? '' : "{$directory}/").$attachment->uuid.'.jpg';

            app(CompressUploadedImageAction::class)->execute($attachment->file_disk, $attachment->file_path, $targetPath);

            $attachment->file_path = $targetPath;
            $attachment->saveQuietly();
        });

        static::deleting(function (self $attachment): void {
            if ($attachment->type === 'photo' && $attachment->file_path !== null) {
                Storage::disk($attachment->file_disk)->delete($attachment->file_path);
            }
        });
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /**
     * Un adjunto type=photo migrado del esquema viejo (Hito 2, ver migración
     * 2027_01_01_000400) pudo no ser una imagen real -- el FileUpload del
     * docente no exigía ->image() antes de este hito. La UI (EvidenceShow
     * del estudiante, Placeholder de solo lectura del docente) usa esto
     * antes de decidir <img> vs. ícono genérico + enlace de descarga, para
     * no arriesgar una imagen rota.
     */
    public function isImage(): bool
    {
        if ($this->type !== 'photo' || $this->file_path === null) {
            return false;
        }

        $mimeType = Storage::disk($this->file_disk)->mimeType($this->file_path);

        return $mimeType !== false && str_starts_with($mimeType, 'image/');
    }
}
