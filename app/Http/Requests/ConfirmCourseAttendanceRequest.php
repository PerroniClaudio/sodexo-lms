<?php

namespace App\Http\Requests;

use App\Models\Module;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ConfirmCourseAttendanceRequest extends FormRequest
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
                    ->where('type', $this->route('course')->type === 'async' ? Module::TYPE_LIVE : Module::TYPE_RESIDENTIAL)
                    ->whereNull('deleted_at')),
            ],
            'minimum_attendance_percentage' => ['required', 'integer', 'min:1', 'max:100'],
            'effective_start_time' => [Rule::requiredIf($this->route('course')->type === 'async'), 'nullable', 'date_format:H:i'],
            'effective_end_time' => [Rule::requiredIf($this->route('course')->type === 'async'), 'nullable', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->route('course')->type !== 'async' || $validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->effectiveEndAt()->lessThanOrEqualTo($this->effectiveStartAt())) {
                    $validator->errors()->add('effective_end_time', __('L\'ora di fine effettiva deve essere successiva all\'ora di inizio effettiva.'));
                }
            },
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

    public function effectiveStartAt(): CarbonImmutable
    {
        return $this->effectiveDateTime((string) $this->validated('effective_start_time'));
    }

    public function effectiveEndAt(): CarbonImmutable
    {
        return $this->effectiveDateTime((string) $this->validated('effective_end_time'));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'module_id' => __('modulo'),
            'minimum_attendance_percentage' => __('percentuale minima di presenza'),
            'effective_start_time' => __('ora di inizio effettiva'),
            'effective_end_time' => __('ora di fine effettiva'),
        ];
    }

    private function effectiveDateTime(string $time): CarbonImmutable
    {
        $module = Module::query()->find($this->validated('module_id'));
        $referenceDate = $module?->appointment_start_time?->format('Y-m-d')
            ?? $module?->appointment_date?->format('Y-m-d')
            ?? now()->format('Y-m-d');

        return CarbonImmutable::createFromFormat('Y-m-d H:i', "{$referenceDate} {$time}");
    }
}
