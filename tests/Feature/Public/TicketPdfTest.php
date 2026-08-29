<?php

use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Services\QrCodeGenerator;
use App\Services\TicketSignatureService;

test('it downloads a PDF containing the order tickets', function () {
    $event = Event::factory()->create(['title' => 'Dakar Jazz Festival']);
    $ticketType = TicketType::factory()->for($event)->create(['name' => 'VIP']);
    $order = Order::factory()->for($event)->paid()->create(['buyer_name' => 'Fatou Sow']);

    Ticket::factory()->for($order)->for($ticketType)->create();

    $response = $this->get(route('checkout.ticket-pdf', $order));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

test('it renders the QR code from qr_payload without needing shared storage', function () {
    // Regression test: the web and worker containers don't share a
    // filesystem in production, so the PDF's QR must be regenerated from
    // qr_payload rather than read from qr_image_path/Storage — this must
    // keep working even when no QR image was ever stored on disk.
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create();
    $order = Order::factory()->for($event)->paid()->create();

    Ticket::factory()->for($order)->for($ticketType)->create(['qr_image_path' => null]);

    $response = $this->get(route('checkout.ticket-pdf', $order));

    $response->assertOk();
});

test('the embedded QR encodes the full payload.signature string, not the payload alone', function () {
    // Regression test: this exact bug shipped once already (the PDF QR was
    // regenerated from qr_payload alone, missing ".signature") — confirmed
    // live by scanning a real downloaded ticket, whose QR decoded to just
    // the payload with no "." at all. verifySignature() requires the full
    // string to ever return valid, so this asserts the QR generator is
    // actually called with it.
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create();
    $order = Order::factory()->for($event)->paid()->create();
    $ticket = Ticket::factory()->for($order)->for($ticketType)->create();

    // The factory's default qr_payload/signature are independently fake
    // (not a real HMAC of that payload) — overwrite them with what
    // GenerateTicketsJob actually stores in production, so this test
    // proves round-trip decodability through the real signature service.
    $signatureService = app(TicketSignatureService::class);
    $expectedQrString = $signatureService->generatePayload($ticket);
    [$payload, $signature] = explode('.', $expectedQrString, 2);
    $ticket->update(['qr_payload' => $payload, 'signature' => $signature]);

    $this->mock(QrCodeGenerator::class, function ($mock) use ($expectedQrString): void {
        $mock->shouldReceive('toPng')
            ->once()
            ->with($expectedQrString)
            ->andReturn('fake-png-bytes');
    });

    $response = $this->get(route('checkout.ticket-pdf', $order));

    $response->assertOk();

    $verification = $signatureService->verifySignature($expectedQrString);
    expect($verification['valid'])->toBeTrue()
        ->and($verification['data']['ticket_id'])->toBe($ticket->id);
});

test('it returns a 404 when the order has no tickets yet', function () {
    $order = Order::factory()->for(Event::factory())->create();

    $response = $this->get(route('checkout.ticket-pdf', $order));

    $response->assertNotFound();
});

test('an unknown confirmation token returns a 404', function () {
    $this->get('/checkout/not-a-real-token/ticket-pdf')->assertNotFound();
});
