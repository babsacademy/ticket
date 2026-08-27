<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowedRoles = array_map(fn (string $role): UserRole => UserRole::from($role), $roles);

        if (! in_array($request->user()?->role, $allowedRoles, strict: true)) {
            abort(403, "Ce compte n'a pas les droits nécessaires pour accéder à cette ressource.");
        }

        return $next($request);
    }
}
