<?php

namespace App\Services\ModuleValidation\Validators;

use App\Models\Module;
use App\Services\ModuleValidation\Contracts\ModuleValidatorInterface;

class ScormValidator implements ModuleValidatorInterface
{
    /**
     * @var array<string>
     */
    private array $errors = [];

    /**
     * Validate the SCORM module.
     *
     * For now, SCORM modules are always valid.
     */
    public function validate(Module $module): bool
    {
        $this->errors = [];

        // For now, SCORM modules are always valid
        return true;
    }

    /**
     * Get validation error messages.
     *
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
