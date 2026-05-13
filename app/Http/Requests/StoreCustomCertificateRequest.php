<?php

namespace App\Http\Requests;

use App\Models\CustomCertificate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomCertificateRequest extends FormRequest
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
            'type' => ['required', 'string', 'in:'.implode(',', CustomCertificate::availableTypes())],
            'template' => [
                'required',
                'file',
                'mimetypes:application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'extensions:docx',
                'max:10240',
            ],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['integer', 'exists:courses,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => __('tipo attestato'),
            'template' => __('file DOCX'),
            'course_ids' => __('corsi'),
        ];
    }
}
