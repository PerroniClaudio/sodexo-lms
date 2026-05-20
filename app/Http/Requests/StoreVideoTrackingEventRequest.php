<?php

namespace App\Http\Requests;

use App\Models\VideoTrackingEvent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVideoTrackingEventRequest extends FormRequest
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
            'session_uuid' => ['required', 'uuid'],
            'event_uuid' => ['required', 'uuid'],
            'event_type' => ['required', 'string', Rule::in(VideoTrackingEvent::TYPES)],
            'occurred_at' => ['required', 'date'],
            'position_second' => ['nullable', 'integer', 'min:0'],
            'max_second_client' => ['nullable', 'integer', 'min:0'],
            'delta_watched_seconds' => ['nullable', 'integer', 'min:0', 'max:120'],
            'from_second' => ['nullable', 'integer', 'min:0'],
            'to_second' => ['nullable', 'integer', 'min:0'],
            'player_ended' => ['nullable', 'boolean'],
            'client_payload' => ['nullable', 'array:event_source,duration_seconds,seeking'],
        ];
    }
}
