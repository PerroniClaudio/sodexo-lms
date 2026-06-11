<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UpdateCourseAttachmentsRequest extends FormRequest
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
            'cover_image' => ['nullable', File::image()->max(1024 * 5)],
            'poster_pdf' => ['nullable', File::types(['pdf'])->max(1024 * 20)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'cover_image' => __('Immagine di copertina'),
            'poster_pdf' => __('Locandina PDF'),
        ];
    }
}
