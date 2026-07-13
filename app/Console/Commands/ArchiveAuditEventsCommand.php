<?php

namespace App\Console\Commands;

use App\Jobs\ArchiveAuditEvents;
use Illuminate\Console\Command;

class ArchiveAuditEventsCommand extends Command
{
    protected $signature = 'audit:archive';

    protected $description = 'Archive audit events older than twenty-four months.';

    public function handle(): int
    {
        ArchiveAuditEvents::dispatch();
        $this->info('Audit archive job dispatched.');

        return self::SUCCESS;
    }
}
