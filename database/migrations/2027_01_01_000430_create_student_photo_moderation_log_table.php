<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Auditoría de intervenciones de personal sobre la foto de perfil de un
// estudiante -- mismo patrón que StudentSessionGrant/DataTreatmentConsent:
// tabla propia del dominio, no genérica. Registra solo intervenciones de
// personal (removed/blocked/unblocked), nunca las acciones del propio
// acudiente (subir/quitar su propia foto) -- para eso ya alcanza el
// updated_at de la columna en users.
//
// Solo created_at (sin updated_at): es un log de solo-append, una fila nunca
// se modifica después de escrita.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_photo_moderation_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->enum('action', ['removed', 'blocked', 'unblocked']);
            $table->foreignId('performed_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_photo_moderation_log');
    }
};
