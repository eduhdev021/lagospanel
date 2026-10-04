<?php

namespace App\Providers;

use App\Models\CannedReply;
use App\Services\SiteConfiguration;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SiteConfiguration::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            try {
                app(SiteConfiguration::class)->apply();
            } catch (\Throwable) {
            }
        }
        Queue::looping(fn () => is_file(storage_path('framework/panel-update-pause')) ? false : null);
        Queue::before(function () {
            app(SiteConfiguration::class)->apply();
        });
        View::composer('admin.tickets', function ($view) {
            $view->with('cannedReplies', CannedReply::where('active', true)->orderBy('title')->orderBy('id')->limit(200)->get(['id', 'title', 'department']));
        });
    }
}
