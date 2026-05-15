<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class ModuleQuizDocumentUpload extends Model
{
    use HasFactory;

    // Stati dell'elaborazione del documento
    public const STATUS_UPLOADED = 'uploaded';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'module_id',
        'uploaded_by',
        'disk',
        'path',
        'status',
        'provider',
        'provider_payload',
        'error_message',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'provider_payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ModuleQuizSubmission::class, 'document_upload_id');
    }

    /**
     * @return Collection<int, string>
     */
    public static function availableStatuses(): Collection
    {
        return collect([
            self::STATUS_UPLOADED,
            self::STATUS_PROCESSING,
            self::STATUS_PROCESSED,
            self::STATUS_FAILED,
        ]);
    }
}
