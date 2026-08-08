<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Models\User;
use App\Modules\Institution\Models\Group;
use Database\Factories\StudentSessionGrantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de auditoría de "entrega de sesión" (Hito 3b-2): un docente cede
 * el control físico de un dispositivo compartido a un estudiante sin login
 * propio (ciclos 1-2), vía auth-switch real (Auth::loginUsingId). Mismo
 * patrón que DataTreatmentConsent — tabla propia del dominio, no genérica.
 *
 * ended_at nulo = entrega todavía activa. Se completa al cerrar manualmente
 * ("Terminar clase") o por expiración automática de inactividad — ver
 * EndStudentSessionAction y App\Http\Middleware\ExpireDeliveredStudentSession.
 */
#[Fillable([
    'student_id', 'granted_by_user_id', 'group_id',
    'started_at', 'ended_at', 'ip_address', 'user_agent',
])]
class StudentSessionGrant extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    protected static function newFactory(): StudentSessionGrantFactory
    {
        return StudentSessionGrantFactory::new();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
