<?php

namespace App\Models;

use Database\Factories\CourseFacultyMemberFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseFacultyMember extends Model
{
    /** @use HasFactory<CourseFacultyMemberFactory> */
    use HasFactory, SoftDeletes;

    public const ROLE_RPF = 'rpf';

    public const ROLE_TEACHER = 'docente';

    public const ROLE_CLASS_TUTOR = 'tutor_aula';

    public const ROLE_MODERATOR = 'moderatore';

    public const ROLE_SECRETARIAT = 'segreteria';

    protected $fillable = [
        'course_id',
        'user_id',
        'name',
        'surname',
        'fiscal_code',
        'role',
        'affiliation',
        'has_compensation',
        'compensation_amount',
    ];

    protected function casts(): array
    {
        return [
            'has_compensation' => 'boolean',
            'compensation_amount' => 'decimal:2',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function roles(): array
    {
        return [
            self::ROLE_RPF,
            self::ROLE_TEACHER,
            self::ROLE_CLASS_TUTOR,
            self::ROLE_MODERATOR,
            self::ROLE_SECRETARIAT,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function roleLabels(): array
    {
        return [
            self::ROLE_RPF => __('Responsabile del Progetto Formativo (RPF)'),
            self::ROLE_TEACHER => __('Docente'),
            self::ROLE_CLASS_TUTOR => __('Tutor d\'aula'),
            self::ROLE_MODERATOR => __('Moderatore'),
            self::ROLE_SECRETARIAT => __('Segreteria'),
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (CourseFacultyMember $member): void {
            if ($member->deleted_at !== null) {
                return;
            }

            $query = static::query()
                ->where('course_id', $member->course_id)
                ->where('role', $member->role)
                ->whereNull('deleted_at')
                ->whereKeyNot($member->getKey());

            if ($member->user_id !== null) {
                $query->where('user_id', $member->user_id);
            } else {
                $query
                    ->whereNull('user_id')
                    ->where('name', $member->name)
                    ->where('surname', $member->surname)
                    ->where('fiscal_code', $member->fiscal_code);
            }

            if ($query->exists()) {
                throw new DomainException('The faculty member already exists for this course and role.');
            }
        });
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
