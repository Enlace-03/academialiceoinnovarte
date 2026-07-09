<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('phase_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('progress_pct')->default(0);
            $table->unsignedSmallInteger('guides_completed')->default(0);
            $table->unsignedSmallInteger('guides_total')->default(0);
            $table->unsignedSmallInteger('forum_participations')->default(0);
            $table->unsignedSmallInteger('chat_participations')->default(0);
            $table->unsignedSmallInteger('evidences_submitted')->default(0);
            $table->unsignedSmallInteger('evidences_total')->default(0);
            $table->string('status', 20)->default('not_started');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'project_id', 'phase_id']);
        });

        Schema::create('student_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('progress_pct')->default(0);
            $table->decimal('avg_rubric_value', 3, 2)->nullable();
            $table->decimal('weekly_pace', 5, 2)->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->unsignedSmallInteger('inactive_days')->default(0);
            $table->string('risk_level', 20)->default('low');
            $table->decimal('risk_score', 5, 2)->default(0);
            $table->timestamps();
            $table->unique(['student_id', 'project_id']);
        });

        Schema::create('performance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->json('metrics');
            $table->timestamps();
            $table->unique(['student_id', 'project_id', 'snapshot_date']);
        });

        Schema::create('predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('prediction_type', 50);
            $table->decimal('value', 8, 2);
            $table->decimal('confidence', 3, 2)->nullable();
            $table->json('reasons');
            $table->string('model_version', 30)->default('rules-v0');
            $table->timestamp('computed_at');
            $table->timestamps();
            $table->index(['student_id', 'project_id', 'prediction_type']);
        });

        Schema::create('risk_alerts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('level', 20);
            $table->text('reason');
            $table->string('triggered_by', 20)->default('rules');
            $table->string('status', 20)->default('open');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'level']);
        });

        Schema::create('avatar_messages', function (Blueprint $table) {
            $table->id();
            $table->string('avatar_key', 30);
            $table->string('context_route', 80);
            $table->string('target_role', 20);
            $table->string('target_grade_range', 15)->default('all');
            $table->text('message');
            $table->string('action_label', 80)->nullable();
            $table->string('action_url', 191)->nullable();
            $table->unsignedTinyInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['context_route', 'target_role', 'is_active']);
        });

        Schema::create('avatar_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('avatar_key', 30);
            $table->string('context_route', 80);
            $table->foreignId('avatar_message_id')->nullable()
                ->constrained()->nullOnDelete();
            $table->string('action', 20);
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('onboarding_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('step_key', 60);
            $table->string('status', 15)->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'step_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_steps');
        Schema::dropIfExists('avatar_interactions');
        Schema::dropIfExists('avatar_messages');
        Schema::dropIfExists('risk_alerts');
        Schema::dropIfExists('predictions');
        Schema::dropIfExists('performance_snapshots');
        Schema::dropIfExists('student_metrics');
        Schema::dropIfExists('student_progress');
    }
};