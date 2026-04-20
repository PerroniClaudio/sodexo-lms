<?php

namespace App\Http\Requests;

use App\Models\Module;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateModuleRequest extends FormRequest
{
    protected ?Module $module = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $module = $this->module();
        $requiresAppointmentDetails = $module !== null
            && Module::requiresAppointmentDetails($module->type);
        $requiresQuizScores = $module?->isQuiz() ?? false;

        return [
            'title' => [
                Rule::requiredIf(fn () => $module !== null && Module::requiresManualTitle($module->type)),
                'nullable',
                'string',
                'max:255',
            ],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::in(Module::availableStatuses())],
            'is_live_teacher' => [
                Rule::requiredIf($module?->type === 'live'),
                'boolean',
            ],
            'appointment_date' => [
                Rule::requiredIf($requiresAppointmentDetails),
                'nullable',
                'date_format:Y-m-d',
            ],
            'appointment_start_time' => [
                Rule::requiredIf($requiresAppointmentDetails),
                'nullable',
                'date_format:H:i',
            ],
            'appointment_end_time' => [
                Rule::requiredIf($requiresAppointmentDetails),
                'nullable',
                'date_format:H:i',
                'after:appointment_start_time',
            ],
            'passing_score' => [
                Rule::requiredIf($requiresQuizScores),
                'nullable',
                'integer',
                'min:0',
            ],
            'max_score' => [
                Rule::requiredIf($requiresQuizScores),
                'nullable',
                'integer',
                'gte:passing_score',
                'min:1',
            ],
        ];
    }

    protected function module(): ?Module
    {
        return $this->module ??= $this->route('module');
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => __('Module title'),
            'description' => __('Descrizione'),
            'status' => __('Stato'),
            'is_live_teacher' => __('Live con docente'),
            'appointment_date' => __('Day'),
            'appointment_start_time' => __('Start time'),
            'appointment_end_time' => __('End time'),
            'passing_score' => __('Passing score'),
            'max_score' => __('Maximum score'),
        ];
    }
}
