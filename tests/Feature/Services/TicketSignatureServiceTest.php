<?php

use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Services\TicketSignatureService;

beforeEach(function () {
    $this->service = new TicketSignatureService;

    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create();
    $order = Order::factory()->for($event)->create();

    $this->ticket = Ticket::factory()->for($order)->for($ticketType)->create();
});

test('generatePayload produces a qr string that verifySignature can decode', function () {
    $qrString = $this->service->generatePayload($this->ticket);

    $result = $this->service->verifySignature($qrString);

    expect($result['valid'])->toBeTrue()
        ->and($result['data']['ticket_id'])->toBe($this->ticket->id)
        ->and($result['data']['event_id'])->toBe($this->ticket->ticketType->event_id)
        ->and($result['data']['holder_id'])->toBe($this->ticket->order->user_id);
});

test('a tampered signature is rejected', function () {
    $qrString = $this->service->generatePayload($this->ticket);
    [$payload] = explode('.', $qrString, 2);

    $tampered = $payload.'.'.str_repeat('0', 64);

    $result = $this->service->verifySignature($tampered);

    expect($result['valid'])->toBeFalse()
        ->and($result['reason'])->toBe('invalid_signature');
});

test('a qr string without a payload.signature separator is rejected', function () {
    $result = $this->service->verifySignature('not-a-valid-qr-string');

    expect($result['valid'])->toBeFalse()
        ->and($result['reason'])->toBe('invalid_signature');
});

test('a payload segment that is not valid base64 is rejected', function () {
    $payload = '###not-base64###';
    $signature = hash_hmac('sha256', $payload, (string) config('tickets.secret'));

    $result = $this->service->verifySignature($payload.'.'.$signature);

    expect($result['valid'])->toBeFalse()
        ->and($result['reason'])->toBe('invalid_signature');
});
