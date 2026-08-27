<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Integrates with the Wave for Business Checkout API.
 *
 * Based on Wave's publicly documented Checkout API (POST /v1/checkout/sessions,
 * redirect to the returned `wave_launch_url`, webhook signed via a Stripe-style
 * `Wave-Signature: t=<timestamp>,v1=<hmac>` header). This has not been exercised
 * against a live Wave account — verify against Wave's current API reference
 * before processing real payments.
 */
class WavePaymentGateway
{
    private const API_BASE_URL = 'https://api.wave.com/v1';

    /**
     * The number of seconds a webhook signature remains valid for, to mitigate replay attacks.
     */
    private const SIGNATURE_TOLERANCE_SECONDS = 300;

    /**
     * Create a Wave checkout session for the given order and return its launch URL.
     *
     * @return array{session_id: string, launch_url: string}
     */
    public function initiate(Order $order, string $successUrl, string $errorUrl): array
    {
        try {
            $response = Http::withToken((string) config('services.wave.secret_key'))
                ->acceptJson()
                ->post(self::API_BASE_URL.'/checkout/sessions', [
                    'amount' => (string) round((float) $order->total_amount),
                    'currency' => 'XOF',
                    'client_reference' => (string) $order->id,
                    'success_url' => $successUrl,
                    'error_url' => $errorUrl,
                ])
                ->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException('Impossible d\'initier le paiement Wave.', previous: $exception);
        }

        $data = $response->json();

        return [
            'session_id' => $data['id'],
            'launch_url' => $data['wave_launch_url'],
        ];
    }

    /**
     * Verify that a webhook payload was genuinely signed by Wave.
     */
    public function verifyWebhookSignature(string $payload, ?string $signatureHeader): bool
    {
        if ($signatureHeader === null || $signatureHeader === '') {
            return false;
        }

        $parts = [];

        foreach (explode(',', $signatureHeader) as $segment) {
            [$key, $value] = array_pad(explode('=', $segment, 2), 2, null);

            if ($key !== null && $value !== null) {
                $parts[$key] = $value;
            }
        }

        if (! isset($parts['t'], $parts['v1']) || ! ctype_digit($parts['t'])) {
            return false;
        }

        $timestamp = (int) $parts['t'];

        if (abs(time() - $timestamp) > self::SIGNATURE_TOLERANCE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", (string) config('services.wave.webhook_secret'));

        return hash_equals($expected, $parts['v1']);
    }
}
