<?php

namespace App\Services\ModuleValidation\Validators;

use App\Models\Module;
use App\Services\ModuleValidation\Contracts\ModuleValidatorInterface;

class ResourceValidator implements ModuleValidatorInterface
{
    /**
     * @var array<string>
     */
    private array $errors = [];

    /**
     * Validate the resource (res) module.
     *
     * For now, resource modules are always valid.
     */
    public function validate(Module $module): bool
    {
        $this->errors = [];

        // For now, resource modules are always valid
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
