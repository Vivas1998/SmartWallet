<?php

namespace App\Providers;

use App\Models\Project;
use App\Policies\ProjectPolicy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
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
        Gate::policy(Project::class, ProjectPolicy::class);

        Auth::guard('web')->setRememberDuration(
            (int) config('smartwallet.remember_duration_minutes')
        );

        View::composer('projects._navigation', function ($view): void {
            if (! Auth::check()) {
                return;
            }

            $view->with('projectSwitcherItems', Auth::user()
                ->projects()
                ->select(['projects.id', 'projects.name', 'projects.color', 'projects.icon', 'projects.archived_at'])
                ->orderByRaw('projects.archived_at is not null')
                ->orderBy('projects.name')
                ->get());
        });
    }
}
