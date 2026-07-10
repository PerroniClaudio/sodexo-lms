<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCourseJobBasedRequirementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'job_based_requirement_ids' => ['nullable', 'array'],
            'job_based_requirement_ids.*' => ['integer', 'exists:job_based_requirements,id'],
        ];
    }
}
