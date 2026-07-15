<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Importazione extends Model
{
    public const TYPE_USERS = 'utenti';

    public const TYPE_USERS_QUICK = 'utenti_rapido';

    public const TYPE_JOB_UNITS = 'unita_lavorative';

    public const TYPE_JOB_TASKS = 'mansioni';

    public const TYPE_USER_JOB_TASKS = 'associazione_utenti_mansioni';

    public const TYPE_USER_COURSES = 'associazione_utenti_corsi';

    public const TYPE_USER_TRAINING_PATHS = 'associazione_utenti_percorsi_formativi';

    public const TYPE_JOB_TASK_RISK_ASSOCIATIONS = 'associazione_mansioni_rischio';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROGRESS = 'progress';

    public const STATUS_FINISHED = 'finished';

    public const STATUS_FAILED = 'failed';

    protected $table = 'importazioni';

    protected $fillable = [
        'import_type',
        'created_by',
        'started_at',
        'finished_at',
        'status',
        'error_message',
        'file_path',
        'original_file_name',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected function casts(): array
    {
        return [
            'created_by' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function trainingPathCourseApprovals(): HasMany
    {
        return $this->hasMany(TrainingPathCourseApproval::class);
    }

    /**
     * @return list<string>
     */
    public static function availableTypes(): array
    {
        return [
            self::TYPE_USERS,
            self::TYPE_USERS_QUICK,
            self::TYPE_JOB_UNITS,
            self::TYPE_JOB_TASKS,
            self::TYPE_USER_JOB_TASKS,
            self::TYPE_USER_COURSES,
            self::TYPE_USER_TRAINING_PATHS,
            self::TYPE_JOB_TASK_RISK_ASSOCIATIONS,
        ];
    }

    public static function typeLabelFor(string $type): string
    {
        return match ($type) {
            self::TYPE_USERS => __('Import utenti completo'),
            self::TYPE_USERS_QUICK => __('Import utenti rapido'),
            self::TYPE_JOB_UNITS => __('Unità lavorative'),
            self::TYPE_JOB_TASKS => __('Mansioni'),
            self::TYPE_USER_JOB_TASKS => __('Associazione utenti mansioni'),
            self::TYPE_USER_COURSES => __('Associazione utenti corsi'),
            self::TYPE_USER_TRAINING_PATHS => __('Associazione utenti percorsi formativi'),
            self::TYPE_JOB_TASK_RISK_ASSOCIATIONS => __('Associazione mansioni rischio'),
            default => $type,
        };
    }

    public function typeLabel(): string
    {
        return self::typeLabelFor((string) $this->import_type);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => __('In coda'),
            self::STATUS_PROGRESS => __('In lavorazione'),
            self::STATUS_FINISHED => __('Completata'),
            self::STATUS_FAILED => __('Fallita'),
            default => (string) $this->status,
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'badge-warning',
            self::STATUS_PROGRESS => 'badge-info',
            self::STATUS_FINISHED => 'badge-success',
            self::STATUS_FAILED => 'badge-error',
            default => 'badge-ghost',
        };
    }

    public function fileName(): string
    {
        return $this->original_file_name ?: basename((string) $this->file_path);
    }
}
