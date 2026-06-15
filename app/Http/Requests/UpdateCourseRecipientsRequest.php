<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourseRecipientsRequest extends FormRequest
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
            'visible_to_all' => ['nullable', 'boolean'],
            'job_role_ids' => ['nullable', 'array'],
            'job_role_ids.*' => ['integer', Rule::exists('job_roles', 'id')],
            'job_task_ids' => ['nullable', 'array'],
            'job_task_ids.*' => ['integer', Rule::exists('job_tasks', 'id')],
            'job_unit_ids' => ['nullable', 'array'],
            'job_unit_ids.*' => ['integer', Rule::exists('job_units', 'id')],
        ];
    }
}
