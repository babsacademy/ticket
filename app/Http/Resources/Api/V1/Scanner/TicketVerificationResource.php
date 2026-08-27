<?php

namespace App\Http\Resources\Api\V1\Scanner;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ticket
 */
class TicketVerificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'holder_name' => $this->holder_name,
            'holder_email' => $this->holder_email,
            'ticket_type' => $this->ticketType->name,
            'event' => [
                'id' => $this->ticketType->event->id,
                'title' => $this->ticketType->event->title,
                'date' => $this->ticketType->event->date->toIso8601String(),
                'venue' => $this->ticketType->event->venue,
            ],
            'scanned_at' => $this->scanned_at?->toIso8601String(),
        ];
    }
}
