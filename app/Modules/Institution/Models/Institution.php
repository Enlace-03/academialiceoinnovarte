<?php

declare(strict_types=1);

namespace App\Modules\Institution\Models;

use Database\Factories\InstitutionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Institution extends Model
{
    use HasFactory;

    protected static function newFactory(): InstitutionFactory
    {
        return InstitutionFactory::new();
    }

    protected $casts = [
        'settings' => 'array',
    ];

    public function schoolGrades(): HasMany
    {
        return $this->hasMany(SchoolGrade::class);
    }

    public function cycles(): HasMany
    {
        return $this->hasMany(Cycle::class);
    }

    public function thinkingFields(): HasMany
    {
        return $this->hasMany(ThinkingField::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(InstitutionSetting::class);
    }
}
