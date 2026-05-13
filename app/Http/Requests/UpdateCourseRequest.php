<?php

namespace App\Http\Requests;

use App\Models\Course;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourseRequest extends FormRequest
{
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
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'year' => ['required', 'integer', 'min:1900', 'max:2100'],
            'expiry_date' => ['required', Rule::date()->format('Y-m-d')],
            'status' => ['required', 'string', Rule::in(Course::availableStatuses())],
            'has_satisfaction_survey' => ['nullable', 'boolean'],
            'satisfaction_survey_required_for_certificate' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => __('Titolo del corso'),
            'description' => __('Descrizione'),
            'year' => __('Anno del corso'),
            'expiry_date' => __('Data scadenza'),
            'status' => __('Stato'),
            'has_satisfaction_survey' => __('Includi questionario di gradimento'),
            'satisfaction_survey_required_for_certificate' => __('Questionario obbligatorio per attestato'),
        ];
    }
}
