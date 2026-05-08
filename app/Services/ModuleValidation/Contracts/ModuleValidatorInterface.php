<?php

namespace App\Services\ModuleValidation\Contracts;

use App\Models\Module;

interface ModuleValidatorInterface
{
    /**
     * Validate the module.
     */
    public function validate(Module $module): bool;

    /**
     * Get validation error messages.
     *
     * @return array<string>
     */
    public function getErrors(): array;
}
