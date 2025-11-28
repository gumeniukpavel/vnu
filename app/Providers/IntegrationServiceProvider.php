<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Integrations\Contracts\{ScheduleProvider,LibraryProvider};
use App\Integrations\Stub\{StubScheduleProvider,StubLibraryProvider};

final class IntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ScheduleProvider::class, function () {
            return match (config('integrations.schedule')) {
                default => new StubScheduleProvider,
            };
        });

        $this->app->bind(LibraryProvider::class, function () {
            return match (config('integrations.library')) {
                default => new StubLibraryProvider,
            };
        });
    }
}
