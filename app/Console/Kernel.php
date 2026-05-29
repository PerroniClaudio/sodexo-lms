<?php

namespace App\Console;

use App\Console\Commands\StartPendingDocumentConversionJobs;
use App\Jobs\SyncCompletedCourseEnrollmentRiskRequirementCertificates;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('videos:sync-mux-status')->everyThirtyMinutes();
        $schedule->command(StartPendingDocumentConversionJobs::class)->everyMinute()->withoutOverlapping();
        $schedule->job(new SyncCompletedCourseEnrollmentRiskRequirementCertificates)->everyThirtyMinutes()->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}
