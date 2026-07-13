<?php

declare(strict_types=1);

namespace App\Modules\Institution\Models;

use Database\Factories\SchoolGradeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolGrade extends Model
{
    use HasFactory;

    protected static function newFactory(): SchoolGradeFactory
    {
        return SchoolGradeFactory::new();
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }
}
