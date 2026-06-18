<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmResAttendanceRequest extends FormRequest
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
            'module_id' => [
                'required',
                'integer',
                Rule::exists('modules', 'id')->where(fn ($query) => $query
                    ->where('belongsTo', (string) $this->route('course')->getKey())
                    ->where('type', 'res')
                    ->whereNull('deleted_at')),
            ],
            'minimum_attendance_percentage' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function moduleId(): int
    {
        return (int) $this->validated('module_id');
    }

    public function minimumAttendancePercentage(): int
    {
        return (int) $this->validated('minimum_attendance_percentage');
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'module_id' => __('modulo RES'),
            'minimum_attendance_percentage' => __('percentuale minima di presenza'),
        ];
    }
}
