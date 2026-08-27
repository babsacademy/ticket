<?php

namespace App\Http\Requests\Public;

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCheckoutRequest extends FormRequest
{
    /**
     * Maximum number of tickets of a single type a guest may buy in one order.
     */
    private const MAX_QUANTITY_PER_TYPE = 10;

    /**
     * Matches Senegalese phone numbers in international (+221/00221 followed
     * by the 9-digit subscriber number) or local (7 followed by 7 digits)
     * format.
     */
    private const SENEGAL_PHONE_REGEX = '/^(\+221\d{9}|00221\d{9}|7\d{7})$/';

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'buyer_name' => ['required', 'string', 'max:255'],
            'buyer_email' => ['nullable', 'email', 'max:255'],
            'buyer_phone' => ['required', 'string', 'regex:'.self::SENEGAL_PHONE_REGEX],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ticket_type_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.self::MAX_QUANTITY_PER_TYPE],
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
            'buyer_phone.regex' => 'Veuillez saisir un numéro de téléphone sénégalais valide (+221771234567, 00221771234567 ou 71234567).',
            'items.*.quantity.max' => 'Vous ne pouvez pas acheter plus de '.self::MAX_QUANTITY_PER_TYPE.' billets par type.',
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $event = $this->route('event');

                if (! $event instanceof Event) {
                    return;
                }

                $ticketTypes = $event->ticketTypes()->get()->keyBy('id');

                foreach ((array) $this->input('items', []) as $index => $item) {
                    $ticketTypeId = $item['ticket_type_id'] ?? null;
                    $quantity = (int) ($item['quantity'] ?? 0);

                    /** @var TicketType|null $ticketType */
                    $ticketType = $ticketTypes->get($ticketTypeId);

                    if ($ticketType === null) {
                        $validator->errors()->add(
                            "items.{$index}.ticket_type_id",
                            'Ce type de billet n\'appartient pas à cet événement.',
                        );

                        continue;
                    }

                    $remaining = $ticketType->quantity - $ticketType->sold_count;

                    if ($quantity > $remaining) {
                        $validator->errors()->add(
                            "items.{$index}.quantity",
                            "Il ne reste que {$remaining} place(s) pour {$ticketType->name}.",
                        );
                    }
                }
            },
        ];
    }
}
