<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Consentimiento de tratamiento de datos personales (Ley 1581). Ver la
// migración create_data_treatment_consents_table para el detalle de los dos
// métodos soportados (admin_confirmed hoy, guardian_self a futuro).
#[Fillable([
    'parent_id',
    'student_id',
    'policy_version',
    'method',
    'confirmed_by_user_id',
    'ip_address',
    'user_agent',
    'accepted_at',
])]
class DataTreatmentConsent extends Model
{
    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }
}
