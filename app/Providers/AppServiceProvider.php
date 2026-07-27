<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Institution\Models\Cycle;
use App\Modules\Institution\Models\Group;
use App\Modules\Institution\Models\SchoolGrade;
use App\Modules\Institution\Models\ThinkingField;
use App\Modules\Institution\Policies\CyclePolicy;
use App\Modules\Institution\Policies\GroupPolicy;
use App\Modules\Institution\Policies\SchoolGradePolicy;
use App\Modules\Institution\Policies\ThinkingFieldPolicy;
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
        Gate::before(fn ($user, $ability) => $user->hasRole('super_admin') ? true : null);
    }
}
