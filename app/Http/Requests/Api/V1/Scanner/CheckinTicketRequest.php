<?php

namespace App\Http\Requests\Api\V1\Scanner;

use Illuminate\Foundation\Http\FormRequest;

class CheckinTicketRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'ticket_id' => ['required', 'integer'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ticket_id.required' => 'Le champ ticket_id est obligatoire.',
        ];
    }
}
