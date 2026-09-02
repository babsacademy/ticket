<?php

namespace App\Services;

use App\Models\Ticket;
use RuntimeException;

class TicketSignatureService
{
    /**
     * Minimum acceptable length for APP_TICKET_SECRET. An empty or short
     * secret would make the HMAC brute-forceable (or, if empty, trivially
     * forgeable), so signing/verifying refuses to run at all rather than
     * silently produce a weak signature.
     */
    private const MIN_SECRET_LENGTH = 32;

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
     *
     * @throws RuntimeException if APP_TICKET_SECRET is missing or too short.
     */
    private function sign(string $payload): string
    {
        $secret = (string) config('tickets.secret');

        if (strlen($secret) < self::MIN_SECRET_LENGTH) {
            throw new RuntimeException(
                'APP_TICKET_SECRET est absent ou trop court (minimum '.self::MIN_SECRET_LENGTH.' caractères) — '
                .'impossible de signer ou vérifier un billet en toute sécurité.',
            );
        }

        return hash_hmac('sha256', $payload, $secret);
    }
}
