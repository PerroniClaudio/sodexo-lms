<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourseDurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'course_start_date' => ['nullable', Rule::date()->format('Y-m-d')],
            'course_end_date' => ['nullable', Rule::date()->format('Y-m-d')],
            'access_closure_date' => ['nullable', Rule::date()->format('Y-m-d')],
            'reporting_date' => ['nullable', Rule::date()->format('Y-m-d')],
            'expiry_date' => ['required', Rule::date()->format('Y-m-d')],
            'course_duration_hours' => ['nullable', 'integer', 'min:0'],
            'interaction_duration_minutes' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'course_start_date' => __('Inizio corso'),
            'course_end_date' => __('Fine corso'),
            'access_closure_date' => __('Chiusura fruizione'),
            'reporting_date' => __('Data di rendicontazione'),
            'expiry_date' => __('Data scadenza'),
            'course_duration_hours' => __('Durata corso'),
            'interaction_duration_minutes' => __('Durata interattività'),
        ];
    }
}
