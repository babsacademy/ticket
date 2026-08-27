<?php

namespace App\Http\Requests\Admin;

use App\Concerns\EventValidationRules;
use App\Models\Event;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateEventRequest extends FormRequest
{
    use EventValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string|Enum>
     */
    public function rules(): array
    {
        $event = $this->route('event');
        $eventId = $event instanceof Event ? $event->id : null;

        return [
            ...$this->eventRules(),
            'ticket_types.*.id' => [
                'nullable',
                'integer',
                Rule::exists('ticket_types', 'id')->where('event_id', $eventId),
            ],
        ];
    }
}
