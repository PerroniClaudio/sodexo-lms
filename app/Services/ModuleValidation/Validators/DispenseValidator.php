<?php

namespace App\Services\ModuleValidation\Validators;

use App\Models\Module;
use App\Services\ModuleValidation\Contracts\ModuleValidatorInterface;

class DispenseValidator implements ModuleValidatorInterface
{
    /**
     * @var array<string>
     */
    private array $errors = [];

    public function validate(Module $module): bool
    {
        $this->errors = $module->teachingMaterials()->exists()
            ? []
            : [__('Il modulo Dispense deve contenere almeno un file.')];

        return $this->errors === [];
    }

    /**
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
