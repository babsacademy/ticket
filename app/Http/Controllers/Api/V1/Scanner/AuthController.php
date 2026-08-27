<?php

namespace App\Http\Controllers\Api\V1\Scanner;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Scanner\ScannerLoginRequest;
use App\Http\Resources\Api\V1\Scanner\ScannerResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Authenticate a scanner agent and issue a Sanctum API token.
     */
    public function store(ScannerLoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Les identifiants fournis sont incorrects.'],
            ]);
        }

        if ($user->role !== UserRole::Scanner) {
            abort(403, "Ce compte n'a pas les droits de scanner.");
        }

        $token = $user->createToken($validated['device_name'] ?? 'scanner-device')->plainTextToken;

        return response()->json([
            'token' => $token,
            'scanner' => new ScannerResource($user),
        ]);
    }
}
