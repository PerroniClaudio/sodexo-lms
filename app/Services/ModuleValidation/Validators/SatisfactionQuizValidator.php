<?php

namespace App\Services\ModuleValidation\Validators;

use App\Models\Module;
use App\Models\SatisfactionSurveyTemplate;
use App\Services\ModuleValidation\Contracts\ModuleValidatorInterface;

class SatisfactionQuizValidator implements ModuleValidatorInterface
{
    /**
     * @var array<string>
     */
    private array $errors = [];

    public function validate(Module $module): bool
    {
        $this->errors = [];

        $module->loadMissing('course');

        $activeTemplate = SatisfactionSurveyTemplate::active();

        if ($activeTemplate === null || ! $activeTemplate->isUsable()) {
            $this->errors[] = __('Configura un questionario di gradimento globale valido prima di pubblicare questo modulo.');
        }

        if ($module->course?->satisfactionModules()->count() !== 1) {
            $this->errors[] = __('Ogni corso puo avere un solo questionario di gradimento.');
        }

        $hasNonSurveyModuleAfterOrAtSameOrder = $module->course?->modules()
            ->whereKeyNot($module->getKey())
            ->where('type', '!=', Module::TYPE_SATISFACTION_QUIZ)
            ->where('order', '>=', $module->order)
            ->exists();

        if ($hasNonSurveyModuleAfterOrAtSameOrder) {
            $this->errors[] = __('Il questionario di gradimento deve essere sempre l\'ultimo modulo del corso.');
        }

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
