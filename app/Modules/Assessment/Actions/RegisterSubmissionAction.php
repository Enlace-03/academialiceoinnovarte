<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Actions;

use App\Models\User;
use App\Modules\Assessment\Events\SubmissionRegistered;
use App\Modules\Assessment\Models\Submission;
use App\Modules\Assessment\Models\SubmissionAttachment;
use App\Modules\Project\Models\ExpectedEvidence;
use App\Modules\Shared\Support\YoutubeUrlDetector;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Usada tanto por el docente (ExpectedEvidencesRelationManager, Filament)
 * como por el estudiante (EvidenceShow, Livewire, Hito 3b-3) -- una sola
 * Action, sin duplicar la lógica de negocio (regla absoluta #1 de
 * CLAUDE.md). Sin historial de versiones (decisión confirmada del Hito 2)
 * -- corregir una entrega devuelta actualiza la misma fila, nunca crea una
 * nueva (unique(expected_evidence_id, student_id) lo garantiza).
 *
 * data['attachments'] (opcional): array de
 * ['type'=>'photo','existing_id'=>?int,'file'=>?UploadedFile] (Livewire,
 * WithFileUploads entrega un temporal sin guardar -- el Action lo guarda),
 * ['type'=>'photo','existing_id'=>?int,'stored_path'=>?string,'original_filename'=>?string]
 * (Filament FileUpload ya guardó el archivo en disco ANTES de que corra el
 * action() de la Action -- a diferencia de Livewire, acá no hay nada que
 * guardar, solo registrar la ruta que Filament ya dejó en 'submissions'), o
 * ['type'=>'link','existing_id'=>?int,'url'=>?string]. Reconciliación
 * (Hito 3b-3, decisiones #2 y #7 del plan): cualquier adjunto ya persistido
 * cuyo id NO venga en la lista se borra (dispara la limpieza física de
 * SubmissionAttachment::deleting()); los que sí vienen con existing_id se
 * conservan tal cual (solo se reordenan si cambió su posición, nunca se
 * recomprimen); los que no traen existing_id son nuevos. Si la clave
 * 'attachments' no viene en $data, los adjuntos existentes no se tocan.
 *
 * existing_id es input de cliente (Livewire/Filament), nunca confiable por
 * sí solo -- un public property de Livewire puede setearse a cualquier
 * valor desde el navegador pese al checksum de la snapshot (el checksum
 * protege el payload serializado entre requests, NO restringe qué valor
 * puede tomar una property pública en la siguiente request). Por eso TODA
 * operación sobre un existing_id va escopeada a $submission->attachments(),
 * nunca a SubmissionAttachment::where() sin filtrar -- un id ajeno
 * simplemente no matchea ninguna fila de esta relación y la operación es un
 * no-op, en vez de tocar el adjunto de otro estudiante.
 *
 * MAX_ATTACHMENTS_PER_SUBMISSION, mismo criterio que
 * CreateForumPostAction::MAX_PHOTOS_PER_POST: regla de negocio real en el
 * Action, no solo validación de UI -- cPanel de producción tiene solo
 * 2.44 GB de cuota total (ver CLAUDE.md "Producción real"). Se cuenta el
 * total final (existentes conservados + nuevos), no solo los nuevos -- si
 * no, conservar varios existentes y agregar unos pocos nuevos esquivaría el
 * tope.
 */
final class RegisterSubmissionAction
{
    public const MAX_ATTACHMENTS_PER_SUBMISSION = 8;

    public function execute(ExpectedEvidence $expectedEvidence, User $student, array $data): Submission
    {
        $attachments = $data['attachments'] ?? null;

        if ($attachments !== null && count($attachments) > self::MAX_ATTACHMENTS_PER_SUBMISSION) {
            throw ValidationException::withMessages([
                'attachments' => 'Máximo '.self::MAX_ATTACHMENTS_PER_SUBMISSION.' adjuntos por entrega.',
            ]);
        }

        $submission = DB::transaction(function () use ($expectedEvidence, $student, $data) {
            $submission = Submission::updateOrCreate(
                [
                    'expected_evidence_id' => $expectedEvidence->id,
                    'student_id' => $student->id,
                ],
                [
                    'text_content' => $data['text_content'] ?? null,
                    'status' => $data['status'] ?? 'submitted',
                    'submitted_at' => $data['submitted_at'] ?? now(),
                ],
            );

            if (array_key_exists('attachments', $data)) {
                $this->reconcileAttachments($submission, $data['attachments']);
            }

            return $submission;
        });

        event(new SubmissionRegistered($submission));

        return $submission;
    }

    /**
     * @param  array<int, array{type: string, existing_id?: ?int, file?: \Illuminate\Http\UploadedFile, url?: string}>  $attachments
     */
    private function reconcileAttachments(Submission $submission, array $attachments): void
    {
        $keptIds = collect($attachments)->pluck('existing_id')->filter()->all();

        $submission->attachments()
            ->whereNotIn('id', $keptIds)
            ->get()
            ->each(fn (SubmissionAttachment $attachment) => $attachment->delete());

        foreach (array_values($attachments) as $order => $attachment) {
            $existingId = $attachment['existing_id'] ?? null;

            if ($existingId !== null) {
                $submission->attachments()->where('id', $existingId)->update(['order' => $order]);

                continue;
            }

            if ($attachment['type'] === 'photo') {
                SubmissionAttachment::create([
                    'submission_id' => $submission->id,
                    'type' => 'photo',
                    'file_disk' => 'local',
                    'file_path' => isset($attachment['stored_path'])
                        ? $attachment['stored_path']
                        : $attachment['file']->store('submissions', 'local'),
                    'original_filename' => isset($attachment['stored_path'])
                        ? ($attachment['original_filename'] ?? null)
                        : $attachment['file']->getClientOriginalName(),
                    'order' => $order,
                ]);

                continue;
            }

            $detection = YoutubeUrlDetector::detect($attachment['url']);

            SubmissionAttachment::create([
                'submission_id' => $submission->id,
                'type' => 'link',
                'url' => $attachment['url'],
                'is_youtube' => $detection['isYoutube'],
                'order' => $order,
            ]);
        }
    }
}
