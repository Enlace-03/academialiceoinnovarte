<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Models;

use App\Models\User;
use App\Modules\Project\Models\Project;
use Database\Factories\ObservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['student_id', 'teacher_id', 'project_id', 'content', 'visible_to_parents'])]
class Observation extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $casts = [
        'visible_to_parents' => 'boolean',
    ];

    protected static function newFactory(): ObservationFactory
    {
        return ObservationFactory::new();
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
