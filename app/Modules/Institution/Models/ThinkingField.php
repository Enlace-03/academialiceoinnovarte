<?php

declare(strict_types=1);

namespace App\Modules\Institution\Models;

use Database\Factories\ThinkingFieldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['institution_id', 'name', 'slug', 'purpose', 'order'])]
class ThinkingField extends Model
{
    use HasFactory;

    protected static function newFactory(): ThinkingFieldFactory
    {
        return ThinkingFieldFactory::new();
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}
