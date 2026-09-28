<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Adjunto opcional de retroalimentación del docente (documento PDF/docx/
// xlsx/pptx), ligado a la Evaluation -- NO a la Submission -- para que nunca
// se mezcle con submission_attachments (lo que sube el estudiante). Sin
// columna type: solo admite documentos. unique(evaluation_id): un solo
// adjunto por evaluación (reevaluar con un archivo nuevo lo reemplaza).
// Sin softDeletes, igual que submission_attachments: es un hijo, el delete
// es físico y su hook limpia el archivo del disco.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_attachments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('evaluation_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('file_disk', 30);
            $table->string('file_path', 191);
            $table->string('original_filename', 191)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_attachments');
    }
};
