<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AuditExport extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = ['requested_by', 'status', 'filters', 'output_disk', 'output_path', 'error_message', 'started_at', 'completed_at'];

    protected $attributes = ['status' => self::STATUS_PENDING];

    protected function casts(): array
    {
        return ['filters' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function hasGeneratedFile(): bool
    {
        return filled($this->output_disk) && filled($this->output_path)
            && Storage::disk($this->output_disk)->exists($this->output_path);
    }
}
