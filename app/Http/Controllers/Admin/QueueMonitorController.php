<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QueueJobMonitor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class QueueMonitorController extends Controller
{
    public function index(): View
    {
        $pendingJobs = $this->jobsInQueue();
        $failedJobs = $this->failedJobs();

        return view('admin.development-tools.queue-monitor', [
            'activeWorkers' => DB::table('queue_worker_heartbeats')
                ->where('last_seen_at', '>=', now()->subSeconds(10))
                ->orderByDesc('last_seen_at')
                ->get(),
            'pendingJobs' => $pendingJobs,
            'completedJobs' => QueueJobMonitor::query()
                ->where('status', 'completed')
                ->latest('finished_at')
                ->limit(50)
                ->get(),
            'failedJobs' => $failedJobs,
        ]);
    }

    /**
     * @return Collection<int, object>
     */
    private function jobsInQueue(): Collection
    {
        return DB::table('jobs')
            ->select(['id', 'queue', 'payload', 'attempts', 'reserved_at', 'available_at', 'created_at'])
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(function (object $job): object {
                $job->job_name = $this->jobName($job->payload);

                return $job;
            });
    }

    /**
     * @return Collection<int, object>
     */
    private function failedJobs(): Collection
    {
        return DB::table('failed_jobs')
            ->select(['uuid', 'connection', 'queue', 'payload', 'exception', 'failed_at'])
            ->orderByDesc('failed_at')
            ->limit(50)
            ->get()
            ->map(function (object $job): object {
                $job->job_name = $this->jobName($job->payload);
                $job->failed_at = Carbon::parse($job->failed_at);

                return $job;
            });
    }

    private function jobName(string $payload): string
    {
        $displayName = json_decode($payload, true)['displayName'] ?? __('Job sconosciuto');

        return class_basename($displayName);
    }
}
