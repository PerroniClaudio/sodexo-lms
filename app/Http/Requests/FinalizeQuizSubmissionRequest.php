<?php

namespace App\Http\Requests;

use App\Models\ModuleQuizQuestion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinalizeQuizSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'answers' => ['required', 'array'],
            'answers.*.question_id' => ['required', Rule::exists(ModuleQuizQuestion::class, 'id')],
            'answers.*.selected_option_key' => ['required', 'string', Rule::in(['A', 'B', 'C', 'D'])],
        ];
    }
}
