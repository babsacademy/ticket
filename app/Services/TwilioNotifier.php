<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;
use Twilio\Rest\Client;

class TwilioNotifier
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client(
            (string) config('services.twilio.sid'),
            (string) config('services.twilio.token'),
        );
    }

    /**
     * Send a WhatsApp message, optionally with an image attachment.
     */
    public function sendWhatsApp(string $to, string $message, ?string $mediaUrl = null): bool
    {
        $from = (string) config('services.twilio.whatsapp_from');

        try {
            $this->client->messages->create("whatsapp:{$to}", array_filter([
                'from' => "whatsapp:{$from}",
                'body' => $message,
                'mediaUrl' => $mediaUrl ? [$mediaUrl] : null,
            ]));

            return true;
        } catch (Throwable $exception) {
            Log::warning('Échec de l\'envoi WhatsApp via Twilio.', [
                'to' => $to,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send a plain SMS message.
     */
    public function sendSms(string $to, string $message): bool
    {
        $from = (string) config('services.twilio.sms_from');

        try {
            $this->client->messages->create($to, [
                'from' => $from,
                'body' => $message,
            ]);

            return true;
        } catch (Throwable $exception) {
            Log::warning('Échec de l\'envoi SMS via Twilio.', [
                'to' => $to,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
