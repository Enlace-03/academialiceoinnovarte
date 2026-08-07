<?php

declare(strict_types=1);

namespace App\Modules\Institution\Models;

use Database\Factories\CycleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['institution_id', 'name', 'order', 'description'])]
class Cycle extends Model
{
    use HasFactory;

    protected static function newFactory(): CycleFactory
    {
        return CycleFactory::new();
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function schoolGrades(): HasMany
    {
        return $this->hasMany(SchoolGrade::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }
}
