<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Models\DataTreatmentConsent;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

// Vincula un acudiente a un estudiante y registra, en la misma transacción,
// el consentimiento de tratamiento de datos que la Ley 1581 exige antes de
// operar con los datos del estudiante.
//
// Hoy el único llamador es GuardiansRelationManager (method admin_confirmed:
// secretaría marca el checkbox). El método guardian_self, para cuando el
// propio acudiente acepte desde /academia, queda fuera de este alcance.
final class RecordDataTreatmentConsentAction
{
    /**
     * @param  array<string, mixed>  $pivotData
     */
    public function execute(
        BelongsToMany $guardiansRelationship,
        User $guardian,
        array $pivotData,
        User $confirmedBy,
    ): DataTreatmentConsent {
        return DB::transaction(function () use ($guardiansRelationship, $guardian, $pivotData, $confirmedBy) {
            $guardiansRelationship->attach($guardian, $pivotData);

            return DataTreatmentConsent::create([
                'parent_id' => $guardian->getKey(),
                'student_id' => $guardiansRelationship->getParent()->getKey(),
                'policy_version' => config('legal.data_treatment_policy_version'),
                'method' => 'admin_confirmed',
                'confirmed_by_user_id' => $confirmedBy->getKey(),
                'accepted_at' => now(),
            ]);
        });
    }
}
