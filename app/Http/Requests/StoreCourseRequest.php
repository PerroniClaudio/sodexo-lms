<?php

namespace App\Http\Requests;

use App\Models\Course;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourseRequest extends FormRequest
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
            'type' => ['required', 'string', 'max:255', Rule::in(Course::availableTypes())],
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
            'type' => __('Tipologia'),
            'has_satisfaction_survey' => __('Includi questionario di gradimento'),
            'satisfaction_survey_required_for_certificate' => __('Questionario obbligatorio per attestato'),
        ];
    }
}
