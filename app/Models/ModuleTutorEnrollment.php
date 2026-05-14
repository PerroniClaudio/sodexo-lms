<?php

namespace App\Models;

use Database\Factories\ModuleTutorEnrollmentFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ModuleTutorEnrollment extends Model
{
    /** @use HasFactory<ModuleTutorEnrollmentFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'module_id',
        'assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ModuleTutorEnrollment $enrollment): void {
            if ($enrollment->deleted_at !== null) {
                return;
            }

            $alreadyAssigned = static::query()
                ->where('user_id', $enrollment->user_id)
                ->where('module_id', $enrollment->module_id)
                ->whereNull('deleted_at')
                ->exists();

            if ($alreadyAssigned) {
                throw new DomainException('The tutor already has an active enrollment for this module.');
            }
        });
    }

    public static function enroll(User $user, Module $module): self
    {
        return static::query()->create([
            'user_id' => $user->getKey(),
            'module_id' => $module->getKey(),
            'assigned_at' => now(),
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
