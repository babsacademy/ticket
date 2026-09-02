<?php

namespace App\Http\Controllers\Api\V1\Scanner;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Scanner\ScannerLoginRequest;
use App\Http\Resources\Api\V1\Scanner\ScannerResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /**
     * Authenticate a scanner agent and issue a Sanctum API token.
     */
    public function store(ScannerLoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::query()->where('email', $validated['email'])->first();

        // Deliberately one generic failure for "wrong password" AND "right
        // password, wrong role": returning a distinct response for the
        // latter (e.g. 403) would let an attacker confirm a guessed
        // credential pair is genuinely valid just by trying it here,
        // regardless of whether that account is actually a scanner.
        if (! $user || ! Hash::check($validated['password'], $user->password) || $user->role !== UserRole::Scanner) {
            throw ValidationException::withMessages([
                'email' => ['Les identifiants fournis sont incorrects.'],
            ]);
        }

        $token = $user->createToken($validated['device_name'] ?? 'scanner-device')->plainTextToken;

        return response()->json([
            'token' => $token,
            'scanner' => new ScannerResource($user),
        ]);
    }

    /**
     * Revoke the token used to authenticate this request. Only the current
     * device's session ends — other devices logged in on the same account
     * keep working.
     */
    public function destroy(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['message' => 'Déconnecté.']);
    }
}
