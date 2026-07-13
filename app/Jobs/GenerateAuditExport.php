<?php

namespace App\Jobs;

use App\Models\AuditEvent;
use App\Models\AuditExport;
use App\Services\AuditTrail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\WithoutRelations;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class GenerateAuditExport implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    #[WithoutRelations]
    public AuditExport $auditExport;

    public function __construct(AuditExport $auditExport)
    {
        $this->auditExport = $auditExport;
    }

    public function handle(AuditTrail $auditTrail): void
    {
        $auditExport = $this->auditExport->fresh();

        if ($auditExport === null || $auditExport->status === AuditExport::STATUS_COMPLETED) {
            return;
        }

        $auditExport->forceFill(['status' => AuditExport::STATUS_PROCESSING, 'started_at' => now(), 'error_message' => null])->save();

        try {
            $path = 'audit-exports/'.$auditExport->getKey().'/audit-events-'.now()->format('YmdHis').'.csv';
            $stream = fopen('php://temp', 'w+b');

            if ($stream === false) {
                throw new RuntimeException('Unable to open audit export stream.');
            }

            fputcsv($stream, ['Occurred at', 'Actor', 'Division', 'Origin', 'Action', 'Subject type', 'Subject id', 'Subject']);
            $this->query($auditExport->filters)->orderByDesc('id')->lazyByIdDesc(500)->each(function (AuditEvent $event) use ($stream): void {
                fputcsv($stream, [$event->occurred_at?->toIso8601String(), $event->actor_label, $event->company_division_id, $event->origin, $event->action, $event->subject_type, $event->subject_id, $event->subject_label]);
            });
            rewind($stream);
            Storage::disk('audit')->writeStream($path, $stream);
            fclose($stream);

            $auditExport->forceFill(['status' => AuditExport::STATUS_COMPLETED, 'output_disk' => 'audit', 'output_path' => $path, 'completed_at' => now()])->save();
            $auditTrail->record('exported', 'AuditExport', $auditExport->getKey(), 'Audit export', metadata: ['export_id' => $auditExport->getKey()]);
        } catch (Throwable $exception) {
            $auditExport->forceFill(['status' => AuditExport::STATUS_FAILED, 'error_message' => $exception->getMessage(), 'completed_at' => now()])->save();
            throw $exception;
        }
    }

    private function query(array $filters)
    {
        return AuditEvent::query()
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('occurred_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('occurred_at', '<=', $date))
            ->when($filters['actor_user_id'] ?? null, fn ($query, $id) => $query->where('actor_user_id', $id))
            ->when($filters['company_division_id'] ?? null, fn ($query, $id) => $query->where('company_division_id', $id))
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when($filters['subject_type'] ?? null, fn ($query, $type) => $query->where('subject_type', $type))
            ->when($filters['subject_id'] ?? null, fn ($query, $id) => $query->where('subject_id', $id));
    }
}
