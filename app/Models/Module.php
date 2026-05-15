<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Module extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_VIDEO = 'video';

    public const TYPE_RESIDENTIAL = 'res';

    public const TYPE_LIVE = 'live';

    public const TYPE_SCORM = 'scorm';

    public const TYPE_LEARNING_QUIZ = 'learning_quiz';

    public const TYPE_SATISFACTION_QUIZ = 'satisfaction_quiz';

    public const TYPES_WITH_APPOINTMENT = [
        self::TYPE_RESIDENTIAL,
        self::TYPE_LIVE,
    ];

    public const TYPES_WITHOUT_MANUAL_TITLE = [
        self::TYPE_LEARNING_QUIZ,
        self::TYPE_SATISFACTION_QUIZ,
    ];

    public const TYPES_WITH_STAFF_ASSIGNMENTS = [
        self::TYPE_VIDEO,
        self::TYPE_RESIDENTIAL,
        self::TYPE_LIVE,
        self::TYPE_SCORM,
    ];

    public const TYPES = [
        self::TYPE_VIDEO,
        self::TYPE_RESIDENTIAL,
        self::TYPE_LIVE,
        self::TYPE_SCORM,
        self::TYPE_LEARNING_QUIZ,
        self::TYPE_SATISFACTION_QUIZ,
    ];

    public const STATUSES = [
        'draft',
        'published',
        'archived',
    ];

    public const PERMITTED_SUBMISSION_ONLINE = 'online';

    public const PERMITTED_SUBMISSION_UPLOAD = 'upload';

    public const PERMITTED_SUBMISSION_ALL = 'all';

    public const PERMITTED_SUBMISSIONS = [
        self::PERMITTED_SUBMISSION_ONLINE,
        self::PERMITTED_SUBMISSION_UPLOAD,
        self::PERMITTED_SUBMISSION_ALL,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'description',
        'type',
        'order',
        'is_live_teacher',
        'mux_live_stream_id',
        'mux_playback_id',
        'mux_stream_key',
        'mux_ingest_url',
        'appointment_date',
        'appointment_start_time',
        'appointment_end_time',
        'status',
        'passing_score',
        'max_score',
        'max_attempts',
        'permitted_submission',
        'belongsTo',
        'video_id', // ID del video associato dalla libreria video Mux
    ];

    /**
     * Relazione con il video associato (libreria video Mux)
     */
    public function video()
    {
        return $this->belongsTo(Video::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'is_live_teacher' => 'boolean',
            'appointment_date' => 'datetime',
            'appointment_start_time' => 'datetime',
            'appointment_end_time' => 'datetime',
            'passing_score' => 'integer',
            'max_score' => 'integer',
            'max_attempts' => 'integer',
        ];
    }

    /**
     * Get the course that owns the module.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'belongsTo');
    }

    /**
     * Get the progress records for the module.
     */
    public function progressRecords(): HasMany
    {
        return $this->hasMany(ModuleProgress::class);
    }

    /**
     * Get the live stream sessions for the module.
     */
    public function liveStreamSessions(): HasMany
    {
        return $this->hasMany(LiveStreamSession::class);
    }

    /**
     * Get the uploaded live stream documents for the module.
     */
    public function liveStreamDocuments(): HasMany
    {
        return $this->hasMany(LiveStreamDocument::class);
    }

    public function scormPackages(): HasMany
    {
        return $this->hasMany(ScormPackage::class);
    }

    public function liveStreamAttendanceMinutes(): HasMany
    {
        return $this->hasMany(LiveStreamAttendanceMinute::class);
    }

    public function teacherEnrollments(): HasMany
    {
        return $this->hasMany(ModuleTeacherEnrollment::class);
    }

    public function tutorEnrollments(): HasMany
    {
        return $this->hasMany(ModuleTutorEnrollment::class);
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'module_teacher_enrollments')
            ->withPivot([
                'id',
                'assigned_at',
                'deleted_at',
            ])
            ->withTimestamps();
    }

    public function tutors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'module_tutor_enrollments')
            ->withPivot([
                'id',
                'assigned_at',
                'deleted_at',
            ])
            ->withTimestamps();
    }

    /**
     * Get the active live stream session for the module.
     */
    public function activeLiveStreamSession(): HasOne
    {
        return $this->hasOne(LiveStreamSession::class)
            ->where('status', LiveStreamSession::STATUS_LIVE)
            ->latestOfMany();
    }

    /**
     * Get the available module types.
     *
     * @return array<int, string>
     */
    public static function availableTypes(): array
    {
        return self::TYPES;
    }

    /**
     * @return array<int, string>
     */
    public static function creatableTypes(): array
    {
        return array_values(array_filter(
            self::availableTypes(),
            fn (string $type): bool => $type !== self::TYPE_SATISFACTION_QUIZ
        ));
    }

    /**
     * Get the translated labels for the available module types.
     *
     * @return array<string, string>
     */
    public static function availableTypeLabels(): array
    {
        return [
            self::TYPE_VIDEO => __('Video'),
            self::TYPE_RESIDENTIAL => __('Residential'),
            self::TYPE_LIVE => __('Live'),
            self::TYPE_SCORM => __('SCORM'),
            self::TYPE_LEARNING_QUIZ => __('Learning quiz'),
            self::TYPE_SATISFACTION_QUIZ => __('Satisfaction quiz'),
        ];
    }

    /**
     * Get the available module statuses.
     *
     * @return array<int, string>
     */
    public static function availableStatuses(): array
    {
        return self::STATUSES;
    }

    /**
     * Get the translated labels for the available module statuses.
     *
     * @return array<string, string>
     */
    public static function availableStatusLabels(): array
    {
        return [
            'draft' => __('Draft'),
            'published' => __('Published'),
            'archived' => __('Archived'),
        ];
    }

    /**
     * Get the available permitted submission types.
     *
     * @return array<int, string>
     */
    public static function availablePermittedSubmissions(): array
    {
        return self::PERMITTED_SUBMISSIONS;
    }

    /**
     * Get the translated labels for the available permitted submission types.
     *
     * @return array<string, string>
     */
    public static function availablePermittedSubmissionLabels(): array
    {
        return [
            self::PERMITTED_SUBMISSION_ONLINE => __('Online'),
            self::PERMITTED_SUBMISSION_UPLOAD => __('Upload'),
            self::PERMITTED_SUBMISSION_ALL => __('Tutti'),
        ];
    }

    /**
     * Determine if the given module type requires a manual title.
     */
    public static function requiresManualTitle(string $type): bool
    {
        return ! in_array($type, self::TYPES_WITHOUT_MANUAL_TITLE, true);
    }

    /**
     * Determine if the given module type requires appointment details.
     */
    public static function requiresAppointmentDetails(string $type): bool
    {
        return in_array($type, self::TYPES_WITH_APPOINTMENT, true);
    }

    /**
     * Get the default title for a given module type.
     */
    public static function defaultTitleForType(string $type): string
    {
        return self::availableTypeLabels()[$type] ?? $type;
    }

    /**
     * Determine if the module is a quiz.
     */
    public function isQuiz(): bool
    {
        return in_array($this->type, [
            self::TYPE_LEARNING_QUIZ,
            self::TYPE_SATISFACTION_QUIZ,
        ], true);
    }

    public function isLearningQuiz(): bool
    {
        return $this->type === self::TYPE_LEARNING_QUIZ;
    }

    public function isSatisfactionQuiz(): bool
    {
        return $this->type === self::TYPE_SATISFACTION_QUIZ;
    }

    /**
     * Determine if the module is a video.
     */
    public function isVideo(): bool
    {
        return $this->type === self::TYPE_VIDEO;
    }

    public function usesRegiaLive(): bool
    {
        return $this->type === self::TYPE_LIVE && ! $this->is_live_teacher;
    }

    public function isScorm(): bool
    {
        return $this->type === self::TYPE_SCORM;
    }

    public function supportsStaffAssignments(): bool
    {
        return in_array($this->type, self::TYPES_WITH_STAFF_ASSIGNMENTS, true);
    }

    /**
     * Domande quiz associate al modulo.
     */
    public function quizQuestions(): HasMany
    {
        return $this->hasMany(ModuleQuizQuestion::class);
    }

    /**
     * Domande quiz valide associate al modulo.
     */
    public function getValidQuizQuestions()
    {
        return $this->quizQuestions()->get()->filter(fn ($q) => $q->isValid());
    }

    /**
     * Punteggio totale delle domande quiz valide associate al modulo.
     */
    public function getValidQuizQuestionsTotalPoints()
    {
        return $this->getValidQuizQuestions()->sum('points');
    }

    /**
     * Determina se il quiz del modulo è valido: deve avere almeno una domanda valida,
     * il punteggio totale delle domande valide deve essere uguale al punteggio massimo del modulo,
     * il punteggio di superamento deve essere minore o uguale al punteggio massimo
     * e (per learning_quiz) deve avere max_attempts valorizzato e > 0.
     */
    public function isValidQuiz(): bool
    {
        $baseValidation = $this->isQuiz()
            && $this->getValidQuizQuestions()->count() > 0
            && $this->getValidQuizQuestionsTotalPoints() === $this->max_score
            && $this->passing_score <= $this->max_score;

        // Per learning_quiz è richiesto anche max_attempts
        if ($this->type === self::TYPE_LEARNING_QUIZ) {
            return $baseValidation && $this->max_attempts !== null && $this->max_attempts > 0;
        }

        return $baseValidation;
    }

    /**
     * Aggiorna il campo max_score del modulo sommando i punti delle domande quiz valide.
     */
    public function updateQuizMaxScore(): void
    {
        $this->max_score = $this->getValidQuizQuestions()->sum('points');
        $this->save();
    }

    public function quizSubmissions(): HasMany
    {
        return $this->hasMany(ModuleQuizSubmission::class);
    }

    public function documentUploads(): HasMany
    {
        return $this->hasMany(ModuleQuizDocumentUpload::class);
    }

    public function submissions(): HasMany
    {
        return $this->quizSubmissions();
    }
}
