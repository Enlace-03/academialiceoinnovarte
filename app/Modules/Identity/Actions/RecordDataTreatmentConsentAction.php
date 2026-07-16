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

            // firstOrCreate, no create(): si el acudiente se desvincula y se
            // vuelve a vincular con el mismo estudiante, ya existe un
            // consentimiento vigente para esa misma policy_version — el
            // unique(parent_id, student_id, policy_version) lo impediría con
            // un create() directo. Se reutiliza el consentimiento existente
            // en vez de duplicar o reventar la transacción completa (que
            // también se llevaría por delante el parent_student recién
            // creado en la línea anterior).
            return DataTreatmentConsent::firstOrCreate([
                'parent_id' => $guardian->getKey(),
                'student_id' => $guardiansRelationship->getParent()->getKey(),
                'policy_version' => config('legal.data_treatment_policy_version'),
            ], [
                'method' => 'admin_confirmed',
                'confirmed_by_user_id' => $confirmedBy->getKey(),
                'accepted_at' => now(),
            ]);
        });
    }
}
