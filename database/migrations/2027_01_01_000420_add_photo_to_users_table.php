<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Foto de perfil de estudiante (ciclos 1-2, subida por el acudiente). Columnas
// directas en users -- relación 1:1, no amerita una tabla de adjuntos como
// SubmissionAttachment. photo_upload_blocked es un bloqueo PERMANENTE hasta
// que personal (coordinator/rector) lo revierta explícitamente -- nunca
// automático ni transitorio, ver student_photo_moderation_log para la
// auditoría de esas intervenciones.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('photo_disk', 30)->nullable()->after('school_grade_id');
            $table->string('photo_path', 191)->nullable()->after('photo_disk');
            $table->boolean('photo_upload_blocked')->default(false)->after('photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['photo_disk', 'photo_path', 'photo_upload_blocked']);
        });
    }
};
