<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

/**
 * Public sign-up is closed — only an admin can create accounts, via the
 * dashboard. Catches GET and POST /register (bookmarks, stale links,
 * search-engine indexing, scripted attempts) and sends visitors to the
 * login page instead of a 404, with an explanatory message.
 */
class RegistrationClosedController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(): RedirectResponse
    {
        return to_route('login')->with('status', "L'inscription n'est pas ouverte au public.");
    }
}
