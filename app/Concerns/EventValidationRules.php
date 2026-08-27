<?php

namespace App\Concerns;

use App\Enums\EventStatus;
use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

trait EventValidationRules
{
    /**
     * Get the validation rules used to validate an event and its ticket types.
     *
     * @return array<string, ValidationRule|array<mixed>|string|Enum>
     */
    protected function eventRules(): array
    {
        return [
            'organizer_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('role', UserRole::Organizer->value),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'date' => ['required', 'date'],
            'venue' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:1'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'status' => ['required', Rule::enum(EventStatus::class)],
            'ticket_types' => ['required', 'array', 'min:1'],
            'ticket_types.*.id' => ['nullable', 'integer', 'exists:ticket_types,id'],
            'ticket_types.*.name' => ['required', 'string', 'max:255'],
            'ticket_types.*.price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
