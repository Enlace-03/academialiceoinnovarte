<?php

namespace Tests\Feature\Assessment;

use App\Models\User;
use App\Modules\Assessment\Actions\RegisterSubmissionAction;
use App\Modules\Project\Models\ExpectedEvidence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Hito 3b-3: RegisterSubmissionAction pasó de un solo archivo escalar a
 * varios adjuntos con reconciliación (conserva/borra/crea). Cubre en
 * particular el hallazgo de seguridad de este mismo hito: un existing_id
 * ajeno (adjunto de OTRA submission) nunca debe poder modificarse desde
 * acá -- ver el docblock de reconcileAttachments().
 */
class RegisterSubmissionActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_saves_multiple_attachments_photo_and_link(): void
    {
        $evidence = ExpectedEvidence::factory()->create();
        $student = User::factory()->create();

        $submission = app(RegisterSubmissionAction::class)->execute($evidence, $student, [
            'attachments' => [
                ['type' => 'photo', 'file' => UploadedFile::fake()->image('foto.jpg', 100, 100)],
                ['type' => 'link', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
                ['type' => 'link', 'url' => 'https://example.com/algo'],
            ],
        ]);

        $this->assertSame(3, $submission->attachments()->count());
        $this->assertDatabaseHas('submission_attachments', [
            'submission_id' => $submission->id,
            'type' => 'photo',
        ]);
        $this->assertDatabaseHas('submission_attachments', [
            'submission_id' => $submission->id,
            'type' => 'link',
            'is_youtube' => true,
        ]);
        $this->assertDatabaseHas('submission_attachments', [
            'submission_id' => $submission->id,
            'type' => 'link',
            'is_youtube' => false,
        ]);
    }

    public function test_resubmission_keeps_attachments_still_listed_and_deletes_removed_ones(): void
    {
        $evidence = ExpectedEvidence::factory()->create();
        $student = User::factory()->create();

        $submission = app(RegisterSubmissionAction::class)->execute($evidence, $student, [
            'attachments' => [
                ['type' => 'photo', 'file' => UploadedFile::fake()->image('a.jpg', 100, 100)],
                ['type' => 'photo', 'file' => UploadedFile::fake()->image('b.jpg', 100, 100)],
            ],
        ]);

        $attachments = $submission->attachments()->orderBy('id')->get();
        $kept = $attachments->first();
        $removedPath = $attachments->last()->file_path;

        Storage::disk('local')->assertExists($removedPath);

        app(RegisterSubmissionAction::class)->execute($evidence, $student, [
            'attachments' => [
                ['type' => 'photo', 'existing_id' => $kept->id],
            ],
        ]);

        $this->assertSame(1, $submission->attachments()->count());
        $this->assertDatabaseHas('submission_attachments', ['id' => $kept->id]);
        Storage::disk('local')->assertMissing($removedPath);
    }

    /**
     * existing_id llega desde una public property de Livewire o desde el
     * Repeater de Filament -- input de cliente, no confiable por sí solo.
     * Este test simula el escenario que motivó escopear la actualización de
     * orden a $submission->attachments() en vez de a
     * SubmissionAttachment::where() sin filtrar.
     */
    public function test_a_foreign_existing_id_from_another_submission_is_not_touched(): void
    {
        $evidenceA = ExpectedEvidence::factory()->create();
        $studentA = User::factory()->create();

        $submissionA = app(RegisterSubmissionAction::class)->execute($evidenceA, $studentA, [
            'attachments' => [['type' => 'link', 'url' => 'https://example.com/a']],
        ]);
        $foreignAttachment = $submissionA->attachments()->first();

        $evidenceB = ExpectedEvidence::factory()->create();
        $studentB = User::factory()->create();

        $submissionB = app(RegisterSubmissionAction::class)->execute($evidenceB, $studentB, [
            'attachments' => [
                ['type' => 'link', 'existing_id' => $foreignAttachment->id],
                ['type' => 'link', 'url' => 'https://example.com/legit'],
            ],
        ]);

        $this->assertSame($submissionA->id, $foreignAttachment->fresh()->submission_id);
        $this->assertSame(0, $foreignAttachment->fresh()->order);
        $this->assertSame(1, $submissionB->attachments()->count());
    }

    public function test_exceeding_max_attachments_throws_validation_exception(): void
    {
        $evidence = ExpectedEvidence::factory()->create();
        $student = User::factory()->create();

        $attachments = collect(range(1, RegisterSubmissionAction::MAX_ATTACHMENTS_PER_SUBMISSION + 1))
            ->map(fn (int $i): array => ['type' => 'link', 'url' => "https://example.com/{$i}"])
            ->all();

        $this->expectException(ValidationException::class);

        app(RegisterSubmissionAction::class)->execute($evidence, $student, ['attachments' => $attachments]);
    }
}
