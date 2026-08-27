<?php

namespace App\Services;

use App\Models\Ticket;

class TicketSignatureService
{
    /**
     * Generate the signed QR string ("payload.signature") for a ticket.
     */
    public function generatePayload(Ticket $ticket): string
    {
        $data = [
            'ticket_id' => $ticket->id,
            'event_id' => $ticket->ticketType->event_id,
            'holder_id' => $ticket->order->user_id,
            'issued_at' => $ticket->created_at->toIso8601String(),
        ];

        $payload = base64_encode((string) json_encode($data));

        return $payload.'.'.$this->sign($payload);
    }

    /**
     * Verify a signed QR string and decode the ticket data it embeds.
     *
     * @return array{valid: true, data: array{ticket_id: int, event_id: int, holder_id: int|null, issued_at: string}}|array{valid: false, reason: string}
     */
    public function verifySignature(string $qrString): array
    {
        $segments = explode('.', $qrString, 2);

        if (count($segments) !== 2) {
            return ['valid' => false, 'reason' => 'invalid_signature'];
        }

        [$payload, $signature] = $segments;

        if (! hash_equals($this->sign($payload), $signature)) {
            return ['valid' => false, 'reason' => 'invalid_signature'];
        }

        $decoded = base64_decode($payload, true);

        if ($decoded === false) {
            return ['valid' => false, 'reason' => 'invalid_signature'];
        }

        /** @var mixed $data */
        $data = json_decode($decoded, true);

        if (! is_array($data) || ! isset($data['ticket_id'], $data['event_id'], $data['issued_at'])) {
            return ['valid' => false, 'reason' => 'invalid_signature'];
        }

        return [
            'valid' => true,
            'data' => [
                'ticket_id' => (int) $data['ticket_id'],
                'event_id' => (int) $data['event_id'],
                'holder_id' => isset($data['holder_id']) ? (int) $data['holder_id'] : null,
                'issued_at' => (string) $data['issued_at'],
            ],
        ];
    }

    /**
     * Compute the HMAC-SHA256 signature of a base64-encoded payload.
     */
    private function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, (string) config('tickets.secret'));
    }
}
