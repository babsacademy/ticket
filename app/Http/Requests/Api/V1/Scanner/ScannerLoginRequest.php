<?php

namespace App\Http\Requests\Api\V1\Scanner;

use Illuminate\Foundation\Http\FormRequest;

class ScannerLoginRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string'],
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
            'email.required' => 'Le champ email est obligatoire.',
            'password.required' => 'Le champ password est obligatoire.',
        ];
    }
}
