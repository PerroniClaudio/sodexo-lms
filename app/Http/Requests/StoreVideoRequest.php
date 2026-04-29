<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreVideoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'superadmin']);
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
            'description' => ['nullable', 'string'],
            'video_file' => [
                'required',
                'file',
                'mimetypes:video/mp4,video/quicktime,video/x-matroska,video/webm,video/ogg,video/x-msvideo,video/x-m4v',
                'mimes:mp4,mov,mkv,webm,ogg,avi,m4v',
                'max:512000', // max 500MB
            ],
        ];
    }
}
