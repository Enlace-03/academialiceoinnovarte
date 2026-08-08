<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Hito 3: enlace mínimo, no automático, entre una entrega y el post de foro
// donde el estudiante participó (para evidencias de tipo "participación en
// foro"). El personal lo asocia a mano al registrar la entrega — no hay
// detección automática de "cuál post cuenta como la entrega".
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->foreignId('forum_post_id')->nullable()->after('expected_evidence_id')
                ->constrained('forum_posts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('forum_post_id');
        });
    }
};
