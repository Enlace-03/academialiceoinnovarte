<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Hito 3: likes solo sobre posts (no sobre hilos). El conteo es público para
// cualquiera con acceso al proyecto; el listado de quién dio like es solo
// para personal (ver ForumPostPolicy).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_post_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forum_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['forum_post_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_post_likes');
    }
};
