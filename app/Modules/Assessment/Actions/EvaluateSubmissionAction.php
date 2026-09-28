<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Actions;

use App\Models\User;
use App\Modules\Assessment\Events\SubmissionEvaluated;
use App\Modules\Assessment\Models\Evaluation;
use App\Modules\Assessment\Models\EvaluationAttachment;
use App\Modules\Assessment\Models\EvaluationResult;
use App\Modules\Assessment\Models\Submission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * unique(submission_id, evaluator_type) permite reevaluar (actualiza la
 * misma Evaluation de ese tipo) sin crear duplicados, y deja espacio para
 * auto/coevaluación futura sin volver a migrar — hoy solo se usa 'teacher'.
 *
 * $feedbackDocument (opcional): documento de retroalimentación del docente,
 * ligado a la Evaluation (EvaluationAttachment), nunca a la Submission.
 * Forma: ['stored_path'=>string,'original_filename'=>?string] (Filament
 * FileUpload ya lo guardó en disco, dentro de 'evaluation-feedback/') o
 * ['file'=>UploadedFile]. Si llega uno nuevo reemplaza al existente (el
 * archivo viejo se borra del disco una vez confirmada la transacción); si
 * no llega ninguno, el existente se conserva tal cual.
 */
final class EvaluateSubmissionAction
{
    public const FEEDBACK_DIRECTORY = 'evaluation-feedback';

    /**
     * @param  array<int, int>  $criteriaResults  ['rubric_criterion_id' => 'rubric_level_id']
     * @param  array{stored_path?: string, original_filename?: ?string, file?: UploadedFile}|null  $feedbackDocument
     */
    public function execute(
        User $evaluator,
        Submission $submission,
        array $criteriaResults,
        ?string $feedback = null,
        string $evaluatorType = 'teacher',
        ?array $feedbackDocument = null,
    ): Evaluation {
        // stored_path viene del cliente (Filament): nunca se confía en una
        // ruta fuera del directorio propio de esta función -- evita apuntar
        // el adjunto a un archivo privado ajeno (p. ej. de otra entrega).
        if (isset($feedbackDocument['stored_path'])
            && (! str_starts_with($feedbackDocument['stored_path'], self::FEEDBACK_DIRECTORY.'/')
                || str_contains($feedbackDocument['stored_path'], '..'))) {
            throw ValidationException::withMessages([
                'feedbackDocument' => 'El documento de retroalimentación no es válido.',
            ]);
        }

        $replacedFile = null;

        $evaluation = DB::transaction(function () use ($evaluator, $submission, $criteriaResults, $feedback, $evaluatorType, $feedbackDocument, &$replacedFile) {
            $evaluation = Evaluation::updateOrCreate(
                [
                    'submission_id' => $submission->id,
                    'evaluator_type' => $evaluatorType,
                ],
                [
                    'evaluated_by' => $evaluator->id,
                    'feedback' => $feedback,
                    'evaluated_at' => now(),
                ],
            );

            foreach ($criteriaResults as $criterionId => $rubricLevelId) {
                EvaluationResult::updateOrCreate(
                    [
                        'evaluation_id' => $evaluation->id,
                        'rubric_criterion_id' => $criterionId,
                    ],
                    ['rubric_level_id' => $rubricLevelId],
                );
            }

            if ($feedbackDocument !== null) {
                $replacedFile = $this->storeFeedbackDocument($evaluation, $feedbackDocument);
            }

            $submission->update(['status' => 'evaluated']);

            event(new SubmissionEvaluated($evaluation));

            return $evaluation;
        });

        if ($replacedFile !== null) {
            Storage::disk($replacedFile['disk'])->delete($replacedFile['path']);
        }

        return $evaluation;
    }

    /**
     * Actualiza en el lugar la fila existente (unique(evaluation_id)) y
     * devuelve el archivo viejo para borrarlo del disco solo si la
     * transacción termina bien.
     *
     * @param  array{stored_path?: string, original_filename?: ?string, file?: UploadedFile}  $document
     * @return array{disk: string, path: string}|null
     */
    private function storeFeedbackDocument(Evaluation $evaluation, array $document): ?array
    {
        if (isset($document['stored_path'])) {
            $path = $document['stored_path'];
            $originalName = $document['original_filename'] ?? null;
        } elseif (isset($document['file'])) {
            $path = $document['file']->store(self::FEEDBACK_DIRECTORY, 'local');
            $originalName = $document['file']->getClientOriginalName();
        } else {
            return null;
        }

        $existing = $evaluation->attachment()->first();

        if ($existing === null) {
            EvaluationAttachment::create([
                'evaluation_id' => $evaluation->id,
                'file_disk' => 'local',
                'file_path' => $path,
                'original_filename' => $originalName,
            ]);

            return null;
        }

        $old = ['disk' => $existing->file_disk, 'path' => $existing->file_path];

        $existing->update([
            'file_disk' => 'local',
            'file_path' => $path,
            'original_filename' => $originalName,
        ]);

        return $old['path'] === $path ? null : $old;
    }
}
