<?php

namespace App\Http\Resources\Api\V1\Scanner;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ticket
 */
class TicketDownloadResource extends JsonResource
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
            'token' => $this->qr_payload,
            'holder_name' => $this->holder_name,
            'ticket_type' => $this->ticketType->name,
        ];
    }
}
