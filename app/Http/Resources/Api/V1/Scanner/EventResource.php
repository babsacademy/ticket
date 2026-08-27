<?php

namespace App\Http\Resources\Api\V1\Scanner;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Event
 */
class EventResource extends JsonResource
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
            'title' => $this->title,
            'date' => $this->date->toIso8601String(),
            'venue' => $this->venue,
            'city' => $this->city,
            'ticket_count' => (int) $this->resource->getAttribute('ticket_count'),
        ];
    }
}
