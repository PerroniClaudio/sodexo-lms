<?php

namespace App\Http\Requests;

use App\Models\Course;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourseProgramRequest extends FormRequest
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
            'program_schedule' => ['nullable', 'array'],
            'program_schedule.*' => ['array:starts_at,ends_at,duration_hours,duration_minutes,teaching_method,topic'],
            'program_schedule.*.starts_at' => ['nullable', 'date_format:H:i'],
            'program_schedule.*.ends_at' => ['nullable', 'date_format:H:i'],
            'program_schedule.*.duration_hours' => ['nullable', 'integer', 'min:0'],
            'program_schedule.*.duration_minutes' => ['nullable', 'integer', 'min:0', 'max:59'],
            'program_schedule.*.teaching_method' => ['nullable', 'string', Rule::in(Course::availableProgramTeachingMethods())],
            'program_schedule.*.topic' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'program_schedule' => collect($this->input('program_schedule', []))
                ->filter(fn (mixed $row): bool => is_array($row) && collect($row)->contains(fn (mixed $value): bool => filled($value)))
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return array<int, array{starts_at: ?string, ends_at: ?string, duration_hours: ?int, duration_minutes: ?int, teaching_method: ?string, topic: ?string}>
     */
    public function programSchedule(): array
    {
        return collect($this->validated('program_schedule', []))
            ->map(fn (array $row): array => [
                'starts_at' => $row['starts_at'] ?? null,
                'ends_at' => $row['ends_at'] ?? null,
                'duration_hours' => filled($row['duration_hours'] ?? null) ? (int) $row['duration_hours'] : null,
                'duration_minutes' => filled($row['duration_minutes'] ?? null) ? (int) $row['duration_minutes'] : null,
                'teaching_method' => $row['teaching_method'] ?? null,
                'topic' => $row['topic'] ?? null,
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'program_schedule' => __('Programma corso'),
            'program_schedule.*.starts_at' => __('Ora inizio'),
            'program_schedule.*.ends_at' => __('Ora fine'),
            'program_schedule.*.duration_hours' => __('Durata modulo ore'),
            'program_schedule.*.duration_minutes' => __('Durata modulo minuti'),
            'program_schedule.*.teaching_method' => __('Metodologie Didattiche'),
            'program_schedule.*.topic' => __('Argomento/sessione'),
        ];
    }
}
