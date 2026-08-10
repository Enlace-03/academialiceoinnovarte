<?php

declare(strict_types=1);

namespace App\Modules\Community\Models;

use App\Modules\Community\Concerns\CompressesPhotoUploads;
use Database\Factories\ForumPostPhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['forum_post_id', 'file_disk', 'file_path', 'original_filename', 'order'])]
class ForumPostPhoto extends Model
{
    use CompressesPhotoUploads, HasFactory, HasUuids;

    protected static function newFactory(): ForumPostPhotoFactory
    {
        return ForumPostPhotoFactory::new();
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(ForumPost::class, 'forum_post_id');
    }
}
