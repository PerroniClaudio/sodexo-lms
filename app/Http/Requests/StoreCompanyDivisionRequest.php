<?php

namespace App\Http\Requests;

use App\Models\CompanyDivision;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyDivisionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole('superadmin') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $companyDivision = $this->route('company_division');

        return [
            'name' => ['required', 'string', 'max:255'],
            'vat_number' => [
                'nullable',
                'string',
                'max:32',
                Rule::unique('company_divisions', 'vat_number')->ignore($companyDivision instanceof CompanyDivision ? $companyDivision->getKey() : null),
            ],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'sync_admins' => ['nullable', 'boolean'],
            'sync_users' => ['nullable', 'boolean'],
            'sync_courses' => ['nullable', 'boolean'],
            'admin_ids' => ['nullable', 'array'],
            'admin_ids.*' => ['integer', Rule::exists('users', 'id')],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', Rule::exists('users', 'id')],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['integer', Rule::exists('courses', 'id')],
        ];
    }
}
