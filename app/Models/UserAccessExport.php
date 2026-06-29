<?php

namespace App\Models;

use App\Support\CloudStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAccessExport extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'requested_by',
        'scope_type',
        'job_dimension',
        'job_dimension_id',
        'date_from',
        'date_to',
        'status',
        'output_disk',
        'output_path',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $userAccessExport): void {
            $userAccessExport->output_disk ??= self::storageDisk();
        });
    }

    protected function casts(): array
    {
        return [
            'requested_by' => 'integer',
            'job_dimension_id' => 'integer',
            'date_from' => 'date',
            'date_to' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public static function storageDisk(): string
    {
        return CloudStorage::disk();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => __('In coda'),
            self::STATUS_PROCESSING => __('In lavorazione'),
            self::STATUS_COMPLETED => __('Completato'),
            self::STATUS_FAILED => __('Fallito'),
            default => (string) $this->status,
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'badge-warning',
            self::STATUS_PROCESSING => 'badge-info',
            self::STATUS_COMPLETED => 'badge-success',
            self::STATUS_FAILED => 'badge-error',
            default => 'badge-ghost',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
        ], true);
    }

    public function hasGeneratedFile(): bool
    {
        return filled($this->output_disk) && filled($this->output_path);
    }

    public function outputFileName(): ?string
    {
        if (! $this->hasGeneratedFile()) {
            return null;
        }

        return basename((string) $this->output_path);
    }

    public function scopeSummary(): string
    {
        $dimension = VideoReportRequest::jobDimensionOptions()[$this->job_dimension] ?? null;
        $label = $dimension['label'] ?? $this->job_dimension;

        return __(':dimension # :id', [
            'dimension' => $label,
            'id' => $this->job_dimension_id,
        ]);
    }
}
