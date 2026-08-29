<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class TwoFactorCodeService
{
    /**
     * How long a generated code stays valid for.
     */
    private const VALID_FOR_MINUTES = 10;

    /**
     * Generate a fresh 6-digit code for the given user, invalidating any
     * code already pending for them, and return the plaintext code to send
     * by email. Only the hash is ever persisted.
     */
    public function generateFor(User $user): string
    {
        $user->twoFactorCodes()->delete();

        $code = (string) random_int(100000, 999999);

        $user->twoFactorCodes()->create([
            'code' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::VALID_FOR_MINUTES),
        ]);

        return $code;
    }

    /**
     * Verify a submitted code against the user's most recent pending code.
     * A wrong code is reported as "invalid" even if the stored code has
     * since expired — expiry is only reported when the code itself matches.
     * A valid code is consumed (deleted) so it can't be replayed.
     *
     * @return array{valid: bool, reason: 'invalid'|'expired'|null}
     */
    public function verify(User $user, string $code): array
    {
        $twoFactorCode = $user->twoFactorCodes()->latest()->first();

        if ($twoFactorCode === null || ! Hash::check($code, $twoFactorCode->code)) {
            return ['valid' => false, 'reason' => 'invalid'];
        }

        if ($twoFactorCode->expires_at->isPast()) {
            return ['valid' => false, 'reason' => 'expired'];
        }

        $twoFactorCode->delete();

        return ['valid' => true, 'reason' => null];
    }
}
