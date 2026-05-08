<?php

namespace App\Observers;

use App\Models\Module;
use App\Services\ModuleValidation\ModuleValidatorService;
use RuntimeException;

class ModuleObserver
{
    public function __construct(
        private readonly ModuleValidatorService $validator
    ) {}

    /**
     * Handle the Module "saving" event.
     *
     *
     * @throws RuntimeException
     */
    public function saving(Module $module): void
    {
        // Prevent changes if course is published
        if ($this->isCoursePublished($module) && $module->isDirty()) {
            throw new RuntimeException(
                'Non è possibile modificare il modulo perché il corso associato è pubblicato.'
            );
        }

        // Prevent changes if module is published (except status field)
        if ($module->exists && $module->getOriginal('status') === 'published' && $this->hasDataChanges($module)) {
            throw new RuntimeException(
                'Non è possibile modificare i dati del modulo quando è pubblicato. Solo lo stato può essere modificato.'
            );
        }
    }

    /**
     * Handle the Module "updating" event.
     *
     *
     * @throws RuntimeException
     */
    public function updating(Module $module): void
    {
        // Check if status is being changed to published
        if ($module->isDirty('status') && $module->status === 'published') {
            $this->validatePublishing($module);
        }

        // Check if status is being changed from published to something else
        if ($module->isDirty('status') && $module->getOriginal('status') === 'published') {
            $this->validateUnpublishing($module);
        }
    }

    /**
     * Validate module can be published.
     *
     *
     * @throws RuntimeException
     */
    private function validatePublishing(Module $module): void
    {
        if (! $this->validator->validate($module)) {
            $errors = $this->validator->getValidationErrors($module);
            $errorMessage = "Non è possibile pubblicare il modulo perché non è valido:\n";
            $errorMessage .= implode("\n", $errors);

            throw new RuntimeException($errorMessage);
        }
    }

    /**
     * Validate module can be unpublished.
     *
     *
     * @throws RuntimeException
     */
    private function validateUnpublishing(Module $module): void
    {
        // Cannot unpublish if module has enrollments (including trashed)
        if ($this->hasEnrollments($module)) {
            throw new RuntimeException(
                'Non è possibile rimuovere la pubblicazione del modulo perché ci sono iscrizioni associate (anche eliminate).'
            );
        }
    }

    /**
     * Check if the module's course is published.
     */
    private function isCoursePublished(Module $module): bool
    {
        if (! $module->course) {
            return false;
        }

        return $module->course->status === 'published';
    }

    /**
     * Check if module has data changes (excluding status field).
     */
    private function hasDataChanges(Module $module): bool
    {
        $dirty = $module->getDirty();

        // Remove status from dirty attributes
        unset($dirty['status']);
        unset($dirty['updated_at']);

        return ! empty($dirty);
    }

    /**
     * Check if module has enrollments (via course enrollments).
     */
    private function hasEnrollments(Module $module): bool
    {
        if (! $module->course) {
            return false;
        }

        // Check for enrollments including soft deleted ones
        return $module->course->enrollments()->withTrashed()->exists();
    }
}
