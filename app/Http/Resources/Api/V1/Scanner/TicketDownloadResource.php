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
            // The offline scanner app matches a scanned code against this
            // token by exact string equality, so it needs the full signed
            // string ("payload.signature"), not just the payload half —
            // see Ticket::fullToken().
            'token' => $this->fullToken(),
            'holder_name' => $this->holder_name,
            'ticket_type' => $this->ticketType->name,
        ];
    }
}
