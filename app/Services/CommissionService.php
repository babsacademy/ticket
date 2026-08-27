<?php

namespace App\Services;

class CommissionService
{
    /**
     * The platform commission rate applied to every order.
     */
    private const COMMISSION_RATE = 0.10;

    /**
     * Split a total amount into the platform commission and the organizer's net share.
     *
     * @return array{total: float, commission: float, net: float}
     */
    public function calculate(float $totalAmount): array
    {
        $commission = round($totalAmount * self::COMMISSION_RATE, 2);

        return [
            'total' => $totalAmount,
            'commission' => $commission,
            'net' => $totalAmount - $commission,
        ];
    }
}
