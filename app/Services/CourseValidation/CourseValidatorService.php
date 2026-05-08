<?php

namespace App\Services\CourseValidation;

use App\Models\Course;
use App\Services\ModuleValidation\ModuleValidatorService;

class CourseValidatorService
{
    public function __construct(
        private readonly ModuleValidatorService $moduleValidator
    ) {}

    /**
     * Validate a course.
     *
     * A course is valid only if all its modules are valid.
     */
    public function validate(Course $course): bool
    {
        $modules = $course->modules;

        if ($modules->isEmpty()) {
            return false;
        }

        foreach ($modules as $module) {
            if (! $this->moduleValidator->validate($module)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a course is publishable.
     *
     * A course is publishable only if all modules are valid and published.
     */
    public function isPublishable(Course $course): bool
    {
        $modules = $course->modules;

        if ($modules->isEmpty()) {
            return false;
        }

        foreach ($modules as $module) {
            // Module must be valid
            if (! $this->moduleValidator->validate($module)) {
                return false;
            }

            // Module must be published
            if ($module->status !== 'published') {
                return false;
            }
        }

        return true;
    }

    /**
     * Get validation errors for a course.
     *
     * @return array<string>
     */
    public function getValidationErrors(Course $course): array
    {
        $errors = [];
        $modules = $course->modules;

        if ($modules->isEmpty()) {
            $errors[] = 'Il corso deve avere almeno un modulo.';

            return $errors;
        }

        foreach ($modules as $module) {
            $moduleErrors = $this->moduleValidator->getValidationErrors($module);

            if (! empty($moduleErrors)) {
                $errors[] = "Modulo \"{$module->title}\" (ID: {$module->id}):";
                foreach ($moduleErrors as $error) {
                    $errors[] = "  - {$error}";
                }
            }
        }

        return $errors;
    }

    /**
     * Get publishability errors for a course.
     *
     * @return array<string>
     */
    public function getPublishabilityErrors(Course $course): array
    {
        $errors = [];
        $modules = $course->modules;

        if ($modules->isEmpty()) {
            $errors[] = 'Il corso deve avere almeno un modulo per essere pubblicato.';

            return $errors;
        }

        foreach ($modules as $module) {
            // Check validity
            $moduleErrors = $this->moduleValidator->getValidationErrors($module);

            if (! empty($moduleErrors)) {
                $errors[] = "Modulo \"{$module->title}\" (ID: {$module->id}) non è valido:";
                foreach ($moduleErrors as $error) {
                    $errors[] = "  - {$error}";
                }
            }

            // Check publication status
            if ($module->status !== 'published') {
                $errors[] = "Modulo \"{$module->title}\" (ID: {$module->id}) deve essere pubblicato.";
            }
        }

        return $errors;
    }
}
