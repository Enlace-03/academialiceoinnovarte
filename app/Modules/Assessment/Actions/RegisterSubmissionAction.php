<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Actions;

use App\Models\User;
use App\Modules\Assessment\Models\Submission;
use App\Modules\Project\Models\ExpectedEvidence;

/**
 * Sin portal de estudiante todavía: el personal registra manualmente que un
 * estudiante entregó. Sin historial de versiones (decisión confirmada del
 * Hito 2) — corregir una entrega devuelta actualiza la misma fila, nunca
 * crea una nueva (unique(expected_evidence_id, student_id) lo garantiza).
 */
final class RegisterSubmissionAction
{
    public function execute(ExpectedEvidence $expectedEvidence, User $student, array $data): Submission
    {
        return Submission::updateOrCreate(
            [
                'expected_evidence_id' => $expectedEvidence->id,
                'student_id' => $student->id,
            ],
            [
                'text_content' => $data['text_content'] ?? null,
                'file_disk' => $data['file_disk'] ?? null,
                'file_path' => $data['file_path'] ?? null,
                'original_filename' => $data['original_filename'] ?? null,
                'status' => $data['status'] ?? 'submitted',
                'submitted_at' => $data['submitted_at'] ?? now(),
            ],
        );
    }
}
