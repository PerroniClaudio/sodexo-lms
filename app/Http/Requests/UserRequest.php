<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Autorizzazione base, personalizzare se necessario
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'account_type' => ['required', 'string', 'in:user,admin,docente,tutor'],
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'fiscal_code' => ['required', 'string', 'max:16'],
            // Campi user-only, validazione condizionale
            'is_foreigner_or_immigrant' => ['required_if:account_type,user', 'boolean'],
            'job_title_id' => ['required_if:account_type,user', 'exists:job_titles,id'],
            'job_role_id' => ['required_if:account_type,user', 'exists:job_roles,id'],
            'job_sector_id' => ['required_if:account_type,user', 'exists:job_sectors,id'],
            'job_unit_id' => ['required_if:account_type,user', 'exists:job_units,id'],
            // Campi opzionali ma validi se presenti
            'job_category_id' => ['nullable', 'exists:job_categories,id'],
            'job_level_id' => ['nullable', 'exists:job_levels,id'],
            // Campi facoltativi
            'password' => ['nullable', 'string', 'min:8'],
            'birth_date' => ['nullable', 'date'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:1'],
            'phone_prefix' => ['nullable', 'string', 'max:8'],
            'phone' => ['nullable', 'string', 'max:32'],
            'country' => ['nullable', 'string', 'max:100'], // Verrà convertito nell'id corrispondente
            'region' => ['nullable', 'string', 'max:100'], // Verrà convertito nell'id corrispondente
            'province' => ['nullable', 'string', 'max:100'], // Verrà convertito nell'id corrispondente
            'city' => ['nullable', 'string', 'max:100'], // Verrà convertito nell'id corrispondente
            // 'home_region_id' => ['nullable', 'integer', 'exists:world_divisions,id'],
            // 'home_province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            // 'home_city_id' => ['nullable', 'integer', 'exists:world_cities,id'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:16'],
        ];
        return $rules;
    }
}
