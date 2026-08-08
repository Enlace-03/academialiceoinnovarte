<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Hito 3: respuestas de un solo nivel. parent_post_id apunta a un post raíz
// (parent_post_id NULL); la Action de creación valida en código que ese post
// raíz no tenga a su vez un parent_post_id — la FK por sí sola no impide una
// cadena más profunda.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forum_posts', function (Blueprint $table) {
            $table->foreignId('parent_post_id')->nullable()->after('forum_thread_id')
                ->constrained('forum_posts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('forum_posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_post_id');
        });
    }
};
