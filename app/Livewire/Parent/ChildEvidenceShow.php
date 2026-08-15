<?php

declare(strict_types=1);

namespace App\Livewire\Parent;

use App\Models\User;
use App\Modules\Assessment\Models\RubricLevel;
use App\Modules\Assessment\Models\Submission;
use App\Modules\Project\Models\ExpectedEvidence;
use App\Modules\Project\Models\Project;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Cuarto y último nivel del drill-down: mismo contenido de solo lectura
 * que Student\EvidenceShow (instrucciones, rúbrica con resaltado por
 * criterio, adjuntos ya entregados, retroalimentación) para $child --
 * SIN formulario de entrega, SIN reentrega, SIN ningún método público que
 * mute nada. Componente nuevo y separado, no una adaptación de
 * Student\EvidenceShow (decisión de la auditoría): ese componente tiene
 * seis métodos públicos de escritura (submit(), startResubmission(),
 * addLink(), removeNewPhoto(), removeNewLink(), removeExisting()), todos
 * invocables desde el cliente vía wire:click sin importar qué oculte el
 * Blade -- generalizarlo a un "estudiante objetivo" habría significado
 * revisar la autorización de cada uno de esos métodos sin necesidad real,
 * en un componente que ya pasó varias rondas de revisión encontrando bugs
 * reales.
 *
 * Reutiliza <x-rubric-criteria-table>, <x-youtube-embed> y la misma ruta
 * submissions.attachments.show que ya sirve los adjuntos de
 * Student\EvidenceShow -- esa ruta autoriza exclusivamente contra
 * SubmissionPolicy::view(), que ya tiene su propia rama para acudiente.
 *
 * Solo lectura confirmado explícitamente por reflection en
 * ChildEvidenceShowTest::test_component_has_no_writable_public_methods().
 */
#[Layout('layouts.portal')]
class ChildEvidenceShow extends Component
{
    public User $child;

    public Project $project;

    public ExpectedEvidence $evidence;

    public function mount(User $child, Project $project, ExpectedEvidence $evidence): void
    {
        abort_unless(auth()->user()->hasRole('parent') && auth()->user()->isGuardianOf($child), 403);

        $this->authorize('viewAsGuardian', [$project, $child]);

        if ($evidence->phase->project_id !== $project->id) {
            throw new NotFoundHttpException();
        }

        $this->child = $child;
        $this->project = $project;
        $this->evidence = $evidence->load(['phase', 'rubric.criteria']);
    }

    public function submission(): ?Submission
    {
        return $this->evidence->submissions()
            ->where('student_id', $this->child->id)
            ->with([
                'attachments',
                'evaluations' => fn ($query) => $query->where('evaluator_type', 'teacher')->with('results.rubricLevel'),
            ])
            ->first();
    }

    /**
     * @return array<int, RubricLevel>
     */
    public function resultsByCriterion(): array
    {
        $evaluation = $this->submission()?->evaluations->first();

        if ($evaluation === null) {
            return [];
        }

        return $evaluation->results
            ->mapWithKeys(fn ($result) => [$result->rubric_criterion_id => $result->rubricLevel])
            ->all();
    }

    /**
     * @return array{status: string, level?: ?RubricLevel, feedback?: ?string}
     */
    public function evidenceState(): array
    {
        $submission = $this->submission();

        if ($submission === null) {
            return ['status' => 'pendiente'];
        }

        if ($submission->status === 'returned') {
            return ['status' => 'devuelta', 'feedback' => $submission->evaluations->first()?->feedback];
        }

        $evaluation = $submission->evaluations->first();

        if ($evaluation === null) {
            return ['status' => 'entregada'];
        }

        return [
            'status' => 'evaluada',
            'level' => $evaluation->consolidatedLevel(),
            'feedback' => $evaluation->feedback,
        ];
    }

    public function render()
    {
        return view('livewire.parent.child-evidence-show', [
            'submission' => $this->submission(),
            'evidenceState' => $this->evidenceState(),
            'resultsByCriterion' => $this->resultsByCriterion(),
        ]);
    }
}
