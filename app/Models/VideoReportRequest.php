<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoReportRequest extends Model
{
    public const REPORT_TYPE_VIDEO = 'video';

    public const REPORT_TYPE_LIVE = 'live';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const SCOPE_COURSE = 'course';

    public const SCOPE_JOB_DIMENSION = 'job_dimension';

    public const JOB_DIMENSION_SECTOR = 'job_sector';

    public const JOB_DIMENSION_CATEGORY = 'job_category';

    public const JOB_DIMENSION_LEVEL = 'job_level';

    public const JOB_DIMENSION_TASK = 'job_task';

    public const JOB_DIMENSION_ROLE = 'job_role';

    public const JOB_DIMENSION_UNIT = 'job_unit';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
    ];

    public const SCOPES = [
        self::SCOPE_COURSE,
        self::SCOPE_JOB_DIMENSION,
    ];

    public const REPORT_TYPES = [
        self::REPORT_TYPE_VIDEO,
        self::REPORT_TYPE_LIVE,
    ];

    protected $fillable = [
        'requested_by',
        'status',
        'scope_type',
        'report_type',
        'course_id',
        'job_dimension',
        'job_dimension_id',
        'date_from',
        'date_to',
        'output_disk',
        'output_path',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'report_type' => self::REPORT_TYPE_VIDEO,
        'output_disk' => 's3',
    ];

    protected function casts(): array
    {
        return [
            'course_id' => 'integer',
            'job_dimension_id' => 'integer',
            'date_from' => 'date',
            'date_to' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public static function jobDimensionOptions(): array
    {
        return [
            self::JOB_DIMENSION_SECTOR => [
                'label' => __('Settore'),
                'model' => JobSector::class,
                'user_column' => 'job_sector_id',
            ],
            self::JOB_DIMENSION_CATEGORY => [
                'label' => __('Categoria'),
                'model' => JobCategory::class,
                'user_column' => 'job_category_id',
            ],
            self::JOB_DIMENSION_LEVEL => [
                'label' => __('Livello'),
                'model' => JobLevel::class,
                'user_column' => 'job_level_id',
            ],
            self::JOB_DIMENSION_TASK => [
                'label' => __('Mansione'),
                'model' => JobTask::class,
                'user_column' => 'job_task_id',
            ],
            self::JOB_DIMENSION_ROLE => [
                'label' => __('Ruolo'),
                'model' => JobRole::class,
                'user_column' => 'job_role_id',
            ],
            self::JOB_DIMENSION_UNIT => [
                'label' => __('Unità lavorativa'),
                'model' => JobUnit::class,
                'user_column' => 'job_unit_id',
            ],
        ];
    }

    public static function reportTypeOptions(): array
    {
        return [
            self::REPORT_TYPE_VIDEO => [
                'label' => __('Video'),
            ],
            self::REPORT_TYPE_LIVE => [
                'label' => __('Live'),
            ],
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
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
        if ($this->scope_type === self::SCOPE_COURSE) {
            return __('Corso: :course', [
                'course' => $this->course?->title ?? __('Corso #:id', ['id' => $this->course_id]),
            ]);
        }

        $dimension = static::jobDimensionOptions()[$this->job_dimension] ?? null;
        $label = $dimension['label'] ?? $this->job_dimension;

        return __(':dimension # :id', [
            'dimension' => $label,
            'id' => $this->job_dimension_id,
        ]);
    }

    public function reportTypeLabel(): string
    {
        return static::reportTypeOptions()[$this->report_type]['label'] ?? (string) $this->report_type;
    }
}
