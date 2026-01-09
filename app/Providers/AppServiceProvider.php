<?php

namespace App\Providers;

use App\Models\Diary;
use App\Models\DiaryEntry;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\Task;
use App\Observers\DiaryEntryObserver;
use App\Observers\PatientObserver;
use App\Observers\TaskObserver;
use App\Policies\DiaryPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\PatientPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected array $policies = [
        Diary::class => DiaryPolicy::class,
        Patient::class => PatientPolicy::class,
        Organization::class => OrganizationPolicy::class,
    ];

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
        // Register observers
        DiaryEntry::observe(DiaryEntryObserver::class);
        Task::observe(TaskObserver::class);
        Patient::observe(PatientObserver::class);

        // Register policies
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
