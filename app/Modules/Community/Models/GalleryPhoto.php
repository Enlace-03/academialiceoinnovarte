<?php

declare(strict_types=1);

namespace App\Modules\Community\Models;

use App\Modules\Community\Concerns\CompressesPhotoUploads;
use Database\Factories\GalleryPhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['gallery_post_id', 'file_disk', 'file_path', 'original_filename', 'order'])]
class GalleryPhoto extends Model
{
    use CompressesPhotoUploads, HasFactory, HasUuids;

    protected static function newFactory(): GalleryPhotoFactory
    {
        return GalleryPhotoFactory::new();
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(GalleryPost::class, 'gallery_post_id');
    }
}
