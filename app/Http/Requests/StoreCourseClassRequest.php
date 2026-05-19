<?php

namespace App\Http\Requests;

use App\Models\Course;
use App\Models\Module;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Validator;

class StoreCourseClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->course()?->supportsClasses() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'module_id' => ['required', 'integer', 'exists:modules,id'],
            'name' => ['required', 'string', 'max:255'],
            'schedules' => ['required', 'array', 'min:1'],
            'schedules.*.starts_at_date' => ['required', 'date_format:Y-m-d'],
            'schedules.*.starts_at_time' => ['required', 'date_format:H:i'],
            'schedules.*.ends_at_date' => ['required', 'date_format:Y-m-d'],
            'schedules.*.ends_at_time' => ['required', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if (! $this->moduleBelongsToCourse()) {
                    $validator->errors()->add('module_id', __('Il modulo selezionato non appartiene a questo corso.'));
                }

                if (! $this->moduleSupportsClasses()) {
                    $validator->errors()->add('module_id', __('Puoi creare classi solo per moduli live o residential.'));
                }

                foreach ($this->schedules() as $index => $schedule) {
                    if ($schedule['starts_at']->gte($schedule['ends_at'])) {
                        $validator->errors()->add("schedules.{$index}.ends_at_time", __('La fine della classe deve essere successiva all\'inizio.'));
                    }
                }
            },
        ];
    }

    /**
     * @return Collection<int, array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}>
     */
    public function schedules(): Collection
    {
        return collect($this->input('schedules', []))
            ->map(function (mixed $schedule): array {
                $startsAtDate = data_get($schedule, 'starts_at_date');
                $startsAtTime = data_get($schedule, 'starts_at_time');
                $endsAtDate = data_get($schedule, 'ends_at_date');
                $endsAtTime = data_get($schedule, 'ends_at_time');

                return [
                    'starts_at' => CarbonImmutable::createFromFormat('Y-m-d H:i', sprintf('%s %s', $startsAtDate, $startsAtTime)),
                    'ends_at' => CarbonImmutable::createFromFormat('Y-m-d H:i', sprintf('%s %s', $endsAtDate, $endsAtTime)),
                ];
            })
            ->values();
    }

    protected function course(): ?Course
    {
        return $this->route('course');
    }

    protected function module(): ?Module
    {
        return Module::query()->find($this->integer('module_id'));
    }

    private function moduleBelongsToCourse(): bool
    {
        $course = $this->course();
        $module = $this->module();

        return $course instanceof Course
            && $module instanceof Module
            && (int) $module->belongsTo === (int) $course->getKey();
    }

    private function moduleSupportsClasses(): bool
    {
        return in_array($this->module()?->type, [Module::TYPE_LIVE, Module::TYPE_RESIDENTIAL], true);
    }
}
