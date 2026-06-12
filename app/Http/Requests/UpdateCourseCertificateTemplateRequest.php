<?php

namespace App\Http\Requests;

use App\Models\CustomCertificate;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCourseCertificateTemplateRequest extends FormRequest
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
            'type' => ['required', 'string', 'in:'.implode(',', CustomCertificate::availableTypes())],
            'template' => [
                'required',
                'file',
                'mimetypes:application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'extensions:docx',
                'max:10240',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => __('tipo attestato'),
            'template' => __('file DOCX'),
        ];
    }
}
