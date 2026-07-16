<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Consentimiento de tratamiento de datos personales (Ley 1581 de 2012).
// Registra que el acudiente autorizó el tratamiento de datos del estudiante
// antes de operar con ellos en la plataforma.
//
// El esquema soporta dos métodos desde ya, aunque hoy solo se use uno:
//   - admin_confirmed: secretaría marca un checkbox al vincular acudiente↔estudiante
//     (GuardiansRelationManager). confirmed_by_user_id = quien marcó el checkbox.
//   - guardian_self: el propio acudiente lo acepta desde /academia (futuro, fuera
//     de este alcance). confirmed_by_user_id sería entonces el propio acudiente,
//     e ip_address/user_agent quedarían poblados.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_treatment_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('policy_version', 30);
            $table->enum('method', ['admin_confirmed', 'guardian_self']);
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('accepted_at');
            $table->timestamps();

            $table->unique(['parent_id', 'student_id', 'policy_version'], 'data_treatment_consents_pair_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_treatment_consents');
    }
};
