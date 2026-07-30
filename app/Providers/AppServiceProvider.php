<?php

namespace App\Providers;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\QueueJobMonitor;
use App\Models\User;
use App\Observers\CourseEnrollmentObserver;
use App\Observers\CourseObserver;
use App\Observers\ModuleObserver;
use App\Observers\UserObserver;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
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
        CourseEnrollment::observe(CourseEnrollmentObserver::class);

        if (! app()->environment(['local', 'development'])) {
            return;
        }

        Queue::looping(function (Looping $event): void {
            DB::table('queue_worker_heartbeats')->updateOrInsert(
                ['worker_id' => gethostname().':'.getmypid()],
                [
                    'connection' => $event->connectionName,
                    'queue' => $event->queue,
                    'last_seen_at' => now(),
                ],
            );
        });

        Queue::before(function (JobProcessing $event): void {
            $this->recordQueueJob($event->job, $event->connectionName, 'processing');
        });

        Queue::after(function (JobProcessed $event): void {
            $this->recordQueueJob($event->job, $event->connectionName, 'completed');
        });

        Queue::failing(function (JobFailed $event): void {
            $this->recordQueueJob($event->job, $event->connectionName, 'failed', $event->exception->getMessage());
        });
    }

    private function recordQueueJob(Job $job, string $connectionName, string $status, ?string $errorMessage = null): void
    {
        $monitor = QueueJobMonitor::query()->firstOrNew([
            'uuid' => $job->uuid() ?? $connectionName.'-'.$job->getJobId(),
        ]);

        $monitor->fill([
            'connection' => $connectionName,
            'queue' => $job->getQueue(),
            'job_name' => $job->resolveName(),
            'status' => $status,
            'attempts' => $job->attempts(),
        ]);

        if ($status === 'processing') {
            $monitor->started_at = now();
            $monitor->finished_at = null;
            $monitor->error_message = null;
        }

        if (in_array($status, ['completed', 'failed'], true)) {
            $monitor->finished_at = now();
            $monitor->error_message = $errorMessage;
        }

        $monitor->save();
    }
}
