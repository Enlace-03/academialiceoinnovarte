<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Models;

use App\Models\User;
use App\Modules\Institution\Models\SchoolGrade;
use Database\Factories\ReportCardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Boletín en PDF de un estudiante. Sin period_id: el colegio no tiene
 * periodos académicos (evaluación continua). 'parcial' es un corte en
 * cualquier momento y no se congela; 'total' cierra un grado
 * (school_grade_id + academic_year) y solo hay uno por estudiante -- esa
 * unicidad la hace cumplir GenerateReportCardJob, no la base de datos.
 * Es solo el documento: no modela comisiones de evaluación ni aprobación.
 */
#[Fillable(['student_id', 'school_grade_id', 'academic_year', 'type', 'file_disk', 'file_path', 'generated_by', 'generated_at'])]
class ReportCard extends Model
{
    use HasFactory, HasUuids;

    public const TYPE_PARCIAL = 'parcial';

    public const TYPE_TOTAL = 'total';

    public const TYPES = [self::TYPE_PARCIAL, self::TYPE_TOTAL];

    protected $casts = [
        'academic_year' => 'integer',
        'generated_at' => 'datetime',
    ];

    protected static function newFactory(): ReportCardFactory
    {
        return ReportCardFactory::new();
    }

    protected static function booted(): void
    {
        static::deleting(function (self $reportCard): void {
            Storage::disk($reportCard->file_disk)->delete($reportCard->file_path);
        });
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function schoolGrade(): BelongsTo
    {
        return $this->belongsTo(SchoolGrade::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
