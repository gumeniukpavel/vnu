<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Integrations\Contracts\{ScheduleProvider,LmsProvider,LibraryProvider};
use App\Integrations\Stub\{StubScheduleProvider,StubLmsProvider,StubLibraryProvider};

final class IntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Schedule
        $this->app->bind(ScheduleProvider::class, function () {
            return match (config('integrations.schedule')) {
                default => new StubScheduleProvider,
            };
        });

        // LMS
        $this->app->bind(LmsProvider::class, function () {
            return match (config('integrations.lms')) {
                default => new StubLmsProvider,
            };
        });

        // Library
        $this->app->bind(LibraryProvider::class, function () {
            return match (config('integrations.library')) {
                default => new StubLibraryProvider,
            };
        });
    }
}
