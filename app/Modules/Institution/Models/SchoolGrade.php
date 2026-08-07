<?php

declare(strict_types=1);

namespace App\Modules\Institution\Models;

use App\Models\User;
use Database\Factories\SchoolGradeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['institution_id', 'cycle_id', 'name', 'level', 'is_active'])]
class SchoolGrade extends Model
{
    use HasFactory;

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function newFactory(): SchoolGradeFactory
    {
        return SchoolGradeFactory::new();
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'school_grade_id');
    }
}
