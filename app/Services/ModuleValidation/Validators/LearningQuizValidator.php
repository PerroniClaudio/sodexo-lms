<?php

namespace App\Services\ModuleValidation\Validators;

use App\Models\Module;
use App\Services\ModuleValidation\Contracts\ModuleValidatorInterface;

class LearningQuizValidator implements ModuleValidatorInterface
{
    /**
     * @var array<string>
     */
    private array $errors = [];

    /**
     * Validate the learning quiz module.
     */
    public function validate(Module $module): bool
    {
        $this->errors = [];

        $validQuestions = $module->getValidQuizQuestions();

        // Must have at least 1 valid question
        if ($validQuestions->count() === 0) {
            $this->errors[] = 'Il quiz deve avere almeno una domanda valida.';
        }

        // Validate each question has 4 answers and exactly 1 correct answer
        foreach ($module->quizQuestions as $question) {
            if ($question->answers()->count() !== 4) {
                $this->errors[] = "La domanda \"{$question->text}\" deve avere esattamente 4 risposte.";
            }

            if (! $question->correctAnswer()->exists()) {
                $this->errors[] = "La domanda \"{$question->text}\" deve avere esattamente una risposta corretta.";
            }
        }

        $validQuestionsTotalPoints = $validQuestions->sum('points');

        // max_score must equal sum of valid question points
        if ($module->max_score !== $validQuestionsTotalPoints) {
            $this->errors[] = "Il punteggio massimo ({$module->max_score}) deve essere uguale alla somma dei punti delle domande valide ({$validQuestionsTotalPoints}).";
        }

        // passing_score must be <= max_score
        if ($module->passing_score > $module->max_score) {
            $this->errors[] = "Il punteggio di superamento ({$module->passing_score}) non può essere maggiore del punteggio massimo ({$module->max_score}).";
        }

        // max_attempts must be > 0
        if (! $module->max_attempts || $module->max_attempts <= 0) {
            $this->errors[] = 'Il numero massimo di tentativi deve essere maggiore di 0.';
        }

        return empty($this->errors);
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
