<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Hito 3: columnas de moderación (ocultar, no borrar) para foro y chat.
// forum_threads/forum_posts ya tienen softDeletes para borrado real del
// autor; is_hidden es un estado distinto, reversible, aplicado por
// moderación (docente dueño del proyecto, o coordinador/rector).
return new class extends Migration
{
    public function up(): void
    {
        foreach (['forum_threads', 'forum_posts', 'chat_messages'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->boolean('is_hidden')->default(false);
                $table->timestamp('hidden_at')->nullable();
                $table->foreignId('hidden_by_user_id')->nullable()
                    ->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['forum_threads', 'forum_posts', 'chat_messages'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('hidden_by_user_id');
                $table->dropColumn(['is_hidden', 'hidden_at']);
            });
        }
    }
};
