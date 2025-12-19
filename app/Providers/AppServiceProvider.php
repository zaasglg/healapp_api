<?php

namespace App\Providers;

use App\Models\DiaryEntry;
use App\Observers\DiaryEntryObserver;
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
        DiaryEntry::observe(DiaryEntryObserver::class);
    }
}
