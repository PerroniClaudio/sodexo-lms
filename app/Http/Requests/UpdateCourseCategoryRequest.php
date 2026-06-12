<?php

namespace App\Http\Requests;

use App\Models\CourseCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourseCategoryRequest extends FormRequest
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
        /** @var CourseCategory $courseCategory */
        $courseCategory = $this->route('course_category');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(CourseCategory::class)->ignore($courseCategory)->withoutTrashed(),
            ],
        ];
    }
}
