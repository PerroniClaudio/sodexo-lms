<?php

namespace App\Jobs;

use App\Models\AuditArchive;
use App\Models\AuditEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ArchiveAuditEvents implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function handle(): void
    {
        $cutoff = now()->subMonths(24)->startOfMonth();
        $oldestEventAt = AuditEvent::query()->where('occurred_at', '<', $cutoff)->min('occurred_at');

        if ($oldestEventAt === null) {
            return;
        }

        $periodStart = Carbon::parse($oldestEventAt)->startOfMonth()->toImmutable();
        $periodEnd = $periodStart->endOfMonth();

        if (AuditArchive::query()->whereDate('period_start', $periodStart)->exists()) {
            return;
        }

        $events = AuditEvent::query()->whereBetween('occurred_at', [$periodStart, $periodEnd])->orderBy('id')->get();

        if ($events->isEmpty()) {
            return;
        }

        $jsonLines = $events->map(fn (AuditEvent $event): string => json_encode($event->only(['id', 'occurred_at', 'actor_user_id', 'actor_label', 'company_division_id', 'origin', 'action', 'subject_type', 'subject_id', 'subject_label', 'correlation_id', 'changes', 'metadata']), JSON_THROW_ON_ERROR))->implode("\n")."\n";
        $payload = gzencode($jsonLines, 9);

        if ($payload === false) {
            throw new RuntimeException('Unable to compress audit archive.');
        }

        $path = sprintf('audit-archives/%s/%s.jsonl.gz', $periodStart->format('Y'), $periodStart->format('m'));
        Storage::disk('audit')->put($path, $payload);
        $checksum = hash('sha256', $payload);

        DB::transaction(function () use ($events, $periodStart, $periodEnd, $path, $checksum): void {
            AuditArchive::query()->create(['period_start' => $periodStart, 'period_end' => $periodEnd, 'disk' => 'audit', 'path' => $path, 'checksum' => $checksum, 'event_count' => $events->count(), 'archived_at' => now()]);
            AuditEvent::query()->whereKey($events->modelKeys())->delete();
        });
    }
}
