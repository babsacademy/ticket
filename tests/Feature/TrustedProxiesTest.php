<?php

use App\Models\User;

test('X-Forwarded-For is honored, proving the platform proxy is trusted', function () {
    // Regression test for a real, silent bug: without trustProxies(at: '*')
    // (Railway/Laravel Cloud terminate TLS and forward through a proxy),
    // $request->ip() always returns the proxy's own IP in production — so
    // every IP-keyed rate limiter in AppServiceProvider would see all
    // traffic as coming from one "client", rate-limiting everyone together
    // after the first few requests regardless of who's actually asking.
    //
    // Proven behaviorally via the scanner-login limiter (5/minute per
    // email+IP): if X-Forwarded-For is honored, two different forwarded
    // IPs get two independent buckets for the same email. If it's
    // ignored, both requests are seen as coming from the same real
    // client IP (127.0.0.1 in tests) and share one bucket instead.
    $scanner = User::factory()->scanner()->create();
    $credentials = ['email' => $scanner->email, 'password' => 'wrong-password'];

    for ($i = 0; $i < 5; $i++) {
        $this->withHeaders(['X-Forwarded-For' => '203.0.113.10'])
            ->postJson('/api/v1/scanner/login', $credentials);
    }

    $this->withHeaders(['X-Forwarded-For' => '203.0.113.10'])
        ->postJson('/api/v1/scanner/login', $credentials)
        ->assertStatus(429);

    $fromADifferentForwardedIp = $this->withHeaders(['X-Forwarded-For' => '198.51.100.20'])
        ->postJson('/api/v1/scanner/login', $credentials);

    $fromADifferentForwardedIp->assertStatus(422);
});
