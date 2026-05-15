<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Artisan;

class SyncMuxVideosStatusJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct()
    {
        //
    }

    /**
     * Middleware per evitare job duplicati in coda
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('sync-mux-videos'))
                ->releaseAfter(60)
                ->expireAfter(300),
        ];
    }

    public function handle(): void
    {
        Artisan::call('videos:sync-mux-status');
    }
}
