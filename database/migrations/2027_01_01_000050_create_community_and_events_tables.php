<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Módulos Community + Tracking (parte 1): foros, chat y el log inmutable learning_events.
//
// IMPORTANTE learning_events:
// - Particionada por rango mensual en MySQL. Las tablas particionadas de MySQL
//   NO admiten foreign keys, y la PK debe incluir la columna de particionado.
// - Nunca agregar FKs a esta tabla. La integridad es responsabilidad de la app.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_threads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('phase_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('forum_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forum_thread_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['forum_thread_id', 'created_at']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->timestamps();
            $table->index(['group_id', 'created_at']);
        });

        // learning_events: creada con SQL crudo por el particionado.
        DB::statement(<<<'SQL'
            CREATE TABLE learning_events (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                student_id BIGINT UNSIGNED NOT NULL,
                project_id BIGINT UNSIGNED NULL,
                event_type VARCHAR(50) NOT NULL,
                payload JSON NULL,
                occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id, occurred_at),
                KEY idx_student_project_time (student_id, project_id, occurred_at),
                KEY idx_type_time (event_type, occurred_at)
            ) ENGINE=InnoDB ROW_FORMAT=DYNAMIC
        SQL);
        // El comando programado events:archive debe crear la partición del mes siguiente
        // (REORGANIZE PARTITION pmax) y archivar particiones > 12 meses.
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_events');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('forum_posts');
        Schema::dropIfExists('forum_threads');
    }
};
