<?php

namespace App\Http\Requests;

use App\Models\LanguageLevel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReorderLanguageLevelsRequest extends FormRequest
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
            'language_levels' => ['required', 'array', 'list'],
            'language_levels.*' => [
                'required',
                'integer',
                'distinct:strict',
                Rule::exists(LanguageLevel::class, 'id'),
            ],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $submittedIds = collect($this->input('language_levels', []))
                    ->map(fn (mixed $languageLevelId): string => (string) $languageLevelId)
                    ->values();

                $expectedIds = LanguageLevel::query()
                    ->ordered()
                    ->pluck('id')
                    ->map(fn (mixed $languageLevelId): string => (string) $languageLevelId)
                    ->values();

                if (
                    $submittedIds->count() !== $expectedIds->count()
                    || $submittedIds->diff($expectedIds)->isNotEmpty()
                    || $expectedIds->diff($submittedIds)->isNotEmpty()
                ) {
                    $validator->errors()->add('language_levels', __('L\'ordine dei livelli lingua non è valido.'));
                }
            },
        ];
    }
}
