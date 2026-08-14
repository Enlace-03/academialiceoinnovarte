<?php

declare(strict_types=1);

namespace App\Livewire\Student;

use App\Modules\Assessment\Actions\RegisterSubmissionAction;
use App\Modules\Assessment\Models\Submission;
use App\Modules\Project\Models\ExpectedEvidence;
use App\Modules\Project\Models\Project;
use App\Modules\Shared\Support\YoutubeUrlDetector;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Pantalla de entrega por evidencia, estilo Classroom (Hito 3b-3): a
 * diferencia de ProjectShow (solo lectura de fases/evidencias), acá el
 * estudiante sube su propia entrega -- varios adjuntos (foto + enlaces).
 *
 * withoutScopedBindings() en la ruta, mismo motivo que ForumThreadShow:
 * ExpectedEvidence no cuelga de Project por una relación adivinable por
 * nombre (va por Phase) -- se resuelve 'evidence' por su propio uuid y acá
 * se verifica a mano que pertenezca al {project} de la URL.
 *
 * "editing" arranca en true si todavía no hay entrega (primera vez) y en
 * false si ya hay una -- volver a true solo vía startResubmission(), que la
 * propia SubmissionPolicy::update() limita a status=returned.
 *
 * Reconciliación de adjuntos (decisión #2/#7 del plan): $keptExisting son
 * adjuntos YA persistidos que el estudiante decide conservar (con su id
 * real); $newPhotos/$newLinks son adjuntos nuevos, sin persistir todavía.
 * Nada se borra/crea en BD hasta submit() -- RegisterSubmissionAction hace
 * la reconciliación real (con su propio tope MAX_ATTACHMENTS_PER_SUBMISSION
 * y su propio re-chequeo de ownership vía existing_id, ver ese Action) en
 * una sola transacción. $keptExisting es public property de Livewire, por
 * lo tanto input de cliente no confiable por sí solo -- por eso el Action,
 * no este componente, es quien re-valida cada existing_id contra
 * $submission->attachments() antes de tocar nada.
 */
#[Layout('layouts.portal')]
class EvidenceShow extends Component
{
    use WithFileUploads;

    /** KB, antes de comprimir -- mismo límite que ForumThreadShow. */
    private const MAX_PHOTO_KB = 8192;

    public Project $project;

    public ExpectedEvidence $evidence;

    public bool $editing = false;

    public string $textContent = '';

    /** @var array<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $newPhotos = [];

    public string $linkInput = '';

    /** @var array<int, array{url: string, is_youtube: bool}> */
    public array $newLinks = [];

    /** @var array<int, array{id: int, type: string, url: ?string, file_path: ?string, original_filename: ?string, is_youtube: bool}> */
    public array $keptExisting = [];

    public function mount(Project $project, ExpectedEvidence $evidence): void
    {
        abort_unless(auth()->user()->hasRole('student'), 403);

        $this->authorize('view', $project);

        if ($evidence->phase->project_id !== $project->id) {
            throw new NotFoundHttpException();
        }

        $this->project = $project;
        $this->evidence = $evidence->load(['phase', 'rubric.criteria']);

        $this->editing = $this->submission() === null;
    }

    public function submission(): ?Submission
    {
        return $this->evidence->submissions()
            ->where('student_id', auth()->id())
            ->with([
                'attachments',
                'evaluations' => fn ($query) => $query->where('evaluator_type', 'teacher')->with('results.rubricLevel'),
            ])
            ->first();
    }

    /**
     * Nivel logrado por criterio (resaltado de la tabla de rúbrica, ver
     * x-rubric-criteria-table) -- un criterio sin EvaluationResult (entrega
     * sin evaluar, o evaluación parcial) simplemente no aparece en este
     * array; el componente lo resuelve con `?? null`, sin resaltar nada
     * para ese criterio, sin error.
     *
     * @return array<int, \App\Modules\Assessment\Models\RubricLevel>
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
     * @return array{status: string, level?: \App\Modules\Assessment\Models\RubricLevel|null, feedback?: ?string}
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

    public function startResubmission(): void
    {
        $submission = $this->submission();

        abort_unless($submission !== null && $submission->status === 'returned', 403);

        $this->editing = true;
        $this->textContent = $submission->text_content ?? '';
        $this->keptExisting = $submission->attachments->map(fn ($attachment): array => [
            'id' => $attachment->id,
            'type' => $attachment->type,
            'url' => $attachment->url,
            'file_path' => $attachment->file_path,
            'original_filename' => $attachment->original_filename,
            'is_youtube' => $attachment->is_youtube,
        ])->all();
        $this->newPhotos = [];
        $this->newLinks = [];
        $this->linkInput = '';
    }

    public function addLink(): void
    {
        $this->validate(['linkInput' => 'required|url|max:2000'], [], ['linkInput' => 'enlace']);

        $detection = YoutubeUrlDetector::detect($this->linkInput);

        $this->newLinks[] = ['url' => $this->linkInput, 'is_youtube' => $detection['isYoutube']];
        $this->linkInput = '';
    }

    public function removeNewPhoto(int $index): void
    {
        unset($this->newPhotos[$index]);
        $this->newPhotos = array_values($this->newPhotos);
    }

    public function removeNewLink(int $index): void
    {
        unset($this->newLinks[$index]);
        $this->newLinks = array_values($this->newLinks);
    }

    public function removeExisting(int $attachmentId): void
    {
        $this->keptExisting = array_values(
            array_filter($this->keptExisting, fn (array $attachment): bool => $attachment['id'] !== $attachmentId)
        );
    }

    public function submit(): void
    {
        $submission = $this->submission();

        if ($submission === null) {
            $this->authorize('create', [Submission::class, $this->evidence]);
        } else {
            $this->authorize('update', $submission);
        }

        $this->validate([
            'textContent' => 'nullable|string|max:2000',
            'newPhotos' => 'array',
            'newPhotos.*' => 'image|max:'.self::MAX_PHOTO_KB,
            'newLinks' => 'array',
            'newLinks.*.url' => 'url|max:2000',
        ]);

        $attachments = [];

        foreach ($this->keptExisting as $existing) {
            $attachments[] = ['type' => $existing['type'], 'existing_id' => $existing['id']];
        }

        foreach ($this->newPhotos as $photo) {
            $attachments[] = ['type' => 'photo', 'file' => $photo];
        }

        foreach ($this->newLinks as $link) {
            $attachments[] = ['type' => 'link', 'url' => $link['url']];
        }

        app(RegisterSubmissionAction::class)->execute($this->evidence, auth()->user(), [
            'text_content' => $this->textContent !== '' ? $this->textContent : null,
            'status' => 'submitted',
            'submitted_at' => now(),
            'attachments' => $attachments,
        ]);

        $this->editing = false;
        $this->textContent = '';
        $this->newPhotos = [];
        $this->newLinks = [];
        $this->linkInput = '';
        $this->keptExisting = [];
    }

    public function render()
    {
        return view('livewire.student.evidence-show', [
            'submission' => $this->submission(),
            'evidenceState' => $this->evidenceState(),
            'resultsByCriterion' => $this->resultsByCriterion(),
        ]);
    }
}
