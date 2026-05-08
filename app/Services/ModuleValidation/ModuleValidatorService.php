<?php

namespace App\Services\ModuleValidation;

use App\Models\Module;
use App\Services\ModuleValidation\Contracts\ModuleValidatorInterface;
use App\Services\ModuleValidation\Validators\LearningQuizValidator;
use App\Services\ModuleValidation\Validators\LiveValidator;
use App\Services\ModuleValidation\Validators\ResourceValidator;
use App\Services\ModuleValidation\Validators\SatisfactionQuizValidator;
use App\Services\ModuleValidation\Validators\ScormValidator;
use App\Services\ModuleValidation\Validators\VideoValidator;
use InvalidArgumentException;

class ModuleValidatorService
{
    /**
     * @var array<string, class-string<ModuleValidatorInterface>>
     */
    private array $validators = [
        'video' => VideoValidator::class,
        'learning_quiz' => LearningQuizValidator::class,
        'live' => LiveValidator::class,
        'satisfaction_quiz' => SatisfactionQuizValidator::class,
        'res' => ResourceValidator::class,
        'scorm' => ScormValidator::class,
    ];

    /**
     * Validate a module based on its type.
     */
    public function validate(Module $module): bool
    {
        $validator = $this->getValidator($module->type);

        return $validator->validate($module);
    }

    /**
     * Get validation errors for a module.
     *
     * @return array<string>
     */
    public function getValidationErrors(Module $module): array
    {
        $validator = $this->getValidator($module->type);
        $validator->validate($module);

        return $validator->getErrors();
    }

    /**
     * Get the validator instance for a specific module type.
     *
     *
     * @throws InvalidArgumentException
     */
    private function getValidator(string $type): ModuleValidatorInterface
    {
        if (! isset($this->validators[$type])) {
            throw new InvalidArgumentException("Nessun validator configurato per il tipo di modulo: {$type}");
        }

        $validatorClass = $this->validators[$type];

        return app($validatorClass);
    }

    /**
     * Register a custom validator for a module type.
     *
     * This allows extending the validation system with new module types.
     *
     * @param  class-string<ModuleValidatorInterface>  $validatorClass
     */
    public function registerValidator(string $type, string $validatorClass): void
    {
        $this->validators[$type] = $validatorClass;
    }
}
