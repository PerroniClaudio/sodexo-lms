<?php

namespace App\Observers;

use App\Models\Course;
use App\Services\CourseValidation\CourseValidatorService;
use RuntimeException;

class CourseObserver
{
    public function __construct(
        private readonly CourseValidatorService $validator
    ) {}

    /**
     * Handle the Course "saving" event.
     *
     *
     * @throws RuntimeException
     */
    public function saving(Course $course): void
    {
        // Permetti la modifica dei dati solo se il corso NON era già pubblicato
        // Blocca solo se il corso esisteva già, era pubblicato PRIMA della richiesta e ci sono modifiche ai dati (escluso lo status)
        if (
            $course->exists &&
            $course->getOriginal('status') === 'published' &&
            $this->hasDataChanges($course)
        ) {
            throw new RuntimeException(
                'Non è possibile modificare i dati del corso quando è pubblicato. Solo lo stato può essere modificato.'
            );
        }
    }

    /**
     * Handle the Course "updating" event.
     *
     *
     * @throws RuntimeException
     */
    public function updating(Course $course): void
    {
        // Check if status is being changed to published
        if ($course->isDirty('status') && $course->status === 'published') {
            $this->validatePublishing($course);
        }

        // Check if status is being changed from published to something else
        if ($course->isDirty('status') && $course->getOriginal('status') === 'published') {
            $this->validateUnpublishing($course);
        }
    }

    /**
     * Validate course can be published.
     *
     *
     * @throws RuntimeException
     */
    private function validatePublishing(Course $course): void
    {
        if (! $this->validator->isPublishable($course)) {
            $errors = $this->validator->getPublishabilityErrors($course);
            $errorMessage = "Non è possibile pubblicare il corso perché:\n";
            $errorMessage .= implode("\n", $errors);

            throw new RuntimeException($errorMessage);
        }
    }

    /**
     * Validate course can be unpublished.
     *
     *
     * @throws RuntimeException
     */
    private function validateUnpublishing(Course $course): void
    {
        // Cannot unpublish if course has enrollments (including trashed)
        if ($this->hasEnrollments($course)) {
            throw new RuntimeException(
                'Non è possibile rimuovere la pubblicazione del corso perché ci sono iscrizioni attive (anche eliminate).'
            );
        }
    }

    /**
     * Check if course has data changes (excluding status field).
     */
    private function hasDataChanges(Course $course): bool
    {
        $dirty = $course->getDirty();

        // Remove status and timestamps from dirty attributes
        unset($dirty['status']);
        unset($dirty['updated_at']);

        return ! empty($dirty);
    }

    /**
     * Check if course has enrollments (including soft deleted).
     */
    private function hasEnrollments(Course $course): bool
    {
        return $course->enrollments()->withTrashed()->exists();
    }
}
