<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Actions;

use App\Models\User;
use App\Modules\Assessment\Events\ReportCardGenerated;
use App\Modules\Assessment\Exceptions\ReportCardAlreadyExistsException;
use App\Modules\Assessment\Models\EvaluationResult;
use App\Modules\Assessment\Models\ReportCard;
use App\Modules\Assessment\Models\RubricLevel;
use App\Modules\Assessment\Support\ReportCardScale;
use App\Modules\Institution\Models\Institution;
use App\Modules\Institution\Models\InstitutionSetting;
use App\Modules\Project\Models\Project;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Genera el boletín en PDF de un estudiante (usada por GenerateReportCardJob).
 * Es solo el documento: no modela comisiones de evaluación ni aprobación.
 *
 * Datos: solo evaluaciones del docente (evaluator_type = 'teacher') de
 * proyectos del año lectivo vigente. Nivel por campo de pensamiento y por
 * proyecto = moda de los niveles de los EvaluationResult, empate gana el
 * nivel más bajo (mismo criterio conservador que
 * Evaluation::consolidatedLevel()), convertido con ReportCardScale. Lo que no
 * tiene evaluaciones no se convierte ni se inventa un 1: el caso de ausencia
 * (ReportCardScale::ABSENT) todavía no tiene un dato que lo decida.
 *
 * type='parcial': siempre genera uno nuevo (no se congela).
 * type='total': uno por (student_id, school_grade_id, academic_year); si ya
 * existe falla salvo $regenerate, que lo reemplaza en la misma fila y borra el
 * archivo viejo del disco una vez confirmada la transacción.
 */
final class GenerateReportCardAction
{
    public const DIRECTORY = 'report-cards';

    public function execute(User $student, string $type, bool $regenerate = false, ?User $generatedBy = null): ReportCard
    {
        if (! in_array($type, ReportCard::TYPES, true)) {
            throw new InvalidArgumentException("Tipo de boletín no válido: '{$type}'.");
        }

        if ($student->school_grade_id === null) {
            throw new RuntimeException("El estudiante {$student->id} no tiene grado asignado; no se puede generar el boletín.");
        }

        $academicYear = $this->academicYear();
        $newPath = null;
        $replacedPath = null;

        try {
            $reportCard = DB::transaction(function () use ($student, $type, $regenerate, $generatedBy, $academicYear, &$newPath, &$replacedPath) {
                $existing = null;

                if ($type === ReportCard::TYPE_TOTAL) {
                    $existing = ReportCard::query()
                        ->where('student_id', $student->id)
                        ->where('school_grade_id', $student->school_grade_id)
                        ->where('academic_year', $academicYear)
                        ->where('type', ReportCard::TYPE_TOTAL)
                        ->lockForUpdate()
                        ->first();

                    if ($existing !== null && ! $regenerate) {
                        throw new ReportCardAlreadyExistsException(
                            "Ya existe el boletín total del estudiante {$student->id} para el año {$academicYear}; pasa regenerate para reemplazarlo."
                        );
                    }
                }

                $newPath = self::DIRECTORY.'/'.Str::uuid().'.pdf';
                Storage::disk('local')->put($newPath, $this->renderPdf($student, $type, $academicYear));

                $attributes = [
                    'student_id' => $student->id,
                    'school_grade_id' => $student->school_grade_id,
                    'academic_year' => $academicYear,
                    'type' => $type,
                    'file_disk' => 'local',
                    'file_path' => $newPath,
                    'generated_by' => $generatedBy?->id,
                    'generated_at' => now(),
                ];

                if ($existing === null) {
                    return ReportCard::create($attributes);
                }

                $replacedPath = $existing->file_path;
                $existing->update($attributes);

                return $existing;
            });
        } catch (\Throwable $e) {
            // El PDF nuevo ya pudo escribirse antes de fallar la transacción.
            if ($newPath !== null) {
                Storage::disk('local')->delete($newPath);
            }

            throw $e;
        }

        if ($replacedPath !== null) {
            Storage::disk('local')->delete($replacedPath);
        }

        event(new ReportCardGenerated($reportCard));

        return $reportCard;
    }

    /**
     * Datos que van al PDF, ya convertidos. Público para poder probarlos sin
     * parsear el PDF.
     *
     * @return array{
     *   fields: list<array{name: string, level: string, number: int, performance: string}>,
     *   projects: list<array{title: string, level: string, number: int, performance: string}>
     * }
     */
    public function reportData(User $student, int $academicYear): array
    {
        $levels = RubricLevel::query()->get()->keyBy('id');

        $rows = EvaluationResult::query()
            ->join('evaluations', 'evaluations.id', '=', 'evaluation_results.evaluation_id')
            ->join('submissions', 'submissions.id', '=', 'evaluations.submission_id')
            ->join('expected_evidences', 'expected_evidences.id', '=', 'submissions.expected_evidence_id')
            ->join('phases', 'phases.id', '=', 'expected_evidences.phase_id')
            ->join('projects', 'projects.id', '=', 'phases.project_id')
            ->where('submissions.student_id', $student->id)
            ->where('evaluations.evaluator_type', 'teacher')
            ->where('projects.year', $academicYear)
            ->select('evaluation_results.rubric_level_id', 'projects.id as project_id')
            ->get();

        $levelIdsByProject = $rows->groupBy('project_id')->map(fn (Collection $group) => $group->pluck('rubric_level_id'));

        $projects = Project::query()
            ->with('thinkingFields')
            ->whereIn('id', $levelIdsByProject->keys())
            ->orderBy('title')
            ->get();

        $projectRows = [];
        $levelIdsByField = [];
        $fieldNames = [];

        foreach ($projects as $project) {
            $projectLevelIds = $levelIdsByProject[$project->id];

            $projectRows[] = $this->row($project->title, $this->dominant($projectLevelIds, $levels), 'title');

            foreach ($project->thinkingFields as $field) {
                $fieldNames[$field->id] = ['name' => $field->name, 'order' => $field->order];
                $levelIdsByField[$field->id] = ($levelIdsByField[$field->id] ?? collect())->concat($projectLevelIds);
            }
        }

        uasort($fieldNames, fn (array $a, array $b) => [$a['order'], $a['name']] <=> [$b['order'], $b['name']]);

        $fieldRows = [];
        foreach ($fieldNames as $fieldId => $field) {
            $fieldRows[] = $this->row($field['name'], $this->dominant($levelIdsByField[$fieldId], $levels), 'name');
        }

        return [
            'fields' => $fieldRows,
            'projects' => $projectRows,
        ];
    }

    /**
     * @return array{name?: string, title?: string, level: string, number: int, performance: string}
     */
    private function row(string $label, RubricLevel $level, string $labelKey): array
    {
        $number = ReportCardScale::fromLevelKey($level->key);

        return [
            $labelKey => $label,
            'level' => $level->label,
            'number' => $number,
            'performance' => ReportCardScale::performanceLabel($number),
        ];
    }

    /**
     * Moda de los niveles; empate gana el nivel más bajo (menor `order`).
     *
     * @param  Collection<int, int>  $levelIds
     * @param  Collection<int, RubricLevel>  $levels
     */
    private function dominant(Collection $levelIds, Collection $levels): RubricLevel
    {
        $counts = $levelIds->countBy();
        $max = $counts->max();

        return $counts
            ->filter(fn (int $count) => $count === $max)
            ->keys()
            ->map(fn ($id) => $levels[$id])
            ->sortBy('order')
            ->first();
    }

    private function renderPdf(User $student, string $type, int $academicYear): string
    {
        $data = $this->reportData($student, $academicYear);

        return Pdf::loadView('pdf.report-card', [
            'institution' => Institution::query()->value('name') ?? 'Liceo Innovarte',
            'studentName' => $student->name,
            'gradeName' => $student->schoolGrade?->name,
            'academicYear' => $academicYear,
            'title' => $type === ReportCard::TYPE_TOTAL ? 'Boletín final' : 'Boletín parcial',
            'generatedAt' => now()->format('d/m/Y'),
            'fields' => $data['fields'],
            'projects' => $data['projects'],
        ])->setPaper('letter')->output();
    }

    private function academicYear(): int
    {
        return (int) InstitutionSetting::get('current_academic_year', config('school.current_academic_year'));
    }
}
