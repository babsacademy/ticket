<?php

use App\Services\CommissionService;

test('splits a total amount into a 10 percent commission and the organizer net', function () {
    $result = (new CommissionService)->calculate(10000);

    expect($result)->toBe([
        'total' => 10000.0,
        'commission' => 1000.0,
        'net' => 9000.0,
    ]);
});

test('the commission and net always add back up to the total', function () {
    $result = (new CommissionService)->calculate(4999.99);

    expect($result['commission'] + $result['net'])->toBe($result['total']);
});
