<?php

namespace App\Providers;

use App\Models\Course;
use App\Models\Module;
use App\Models\User;
use App\Observers\CourseObserver;
use App\Observers\ModuleObserver;
use App\Observers\UserObserver;
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
        User::observe(UserObserver::class);
        Module::observe(ModuleObserver::class);
        Course::observe(CourseObserver::class);
    }
}
