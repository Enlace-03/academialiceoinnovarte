<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Assessment\Models\Evaluation;
use App\Modules\Assessment\Models\Observation;
use App\Modules\Assessment\Models\Rubric;
use App\Modules\Assessment\Policies\EvaluationPolicy;
use App\Modules\Assessment\Policies\ObservationPolicy;
use App\Modules\Assessment\Policies\RubricPolicy;
use App\Modules\Community\Models\ChatMessage;
use App\Modules\Community\Models\ForumPost;
use App\Modules\Community\Models\ForumThread;
use App\Modules\Community\Policies\ChatMessagePolicy;
use App\Modules\Community\Policies\ForumPostPolicy;
use App\Modules\Community\Policies\ForumThreadPolicy;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\Group;
use App\Modules\Institution\Models\SchoolGrade;
use App\Modules\Institution\Models\ThinkingField;
use App\Modules\Institution\Policies\CyclePolicy;
use App\Modules\Institution\Policies\GroupPolicy;
use App\Modules\Institution\Policies\SchoolGradePolicy;
use App\Modules\Institution\Policies\ThinkingFieldPolicy;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Observers\ProjectObserver;
use App\Modules\Project\Policies\ProjectPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Cycle::class, CyclePolicy::class);
        Gate::policy(ThinkingField::class, ThinkingFieldPolicy::class);
        Gate::policy(SchoolGrade::class, SchoolGradePolicy::class);
        Gate::policy(Group::class, GroupPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Rubric::class, RubricPolicy::class);
        Gate::policy(Evaluation::class, EvaluationPolicy::class);
        Gate::policy(Observation::class, ObservationPolicy::class);
        Gate::policy(ForumThread::class, ForumThreadPolicy::class);
        Gate::policy(ForumPost::class, ForumPostPolicy::class);
        Gate::policy(ChatMessage::class, ChatMessagePolicy::class);
        Gate::before(fn ($user, $ability) => $user->hasRole('super_admin') ? true : null);

        Project::observe(ProjectObserver::class);
    }
}
