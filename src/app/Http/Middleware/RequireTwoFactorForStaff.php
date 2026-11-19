<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Backlog B1: administrators and moderators must turn on two-factor
 * authentication before they can use the application. Until then, every
 * page behind this middleware sends them to the account page.
 */
class RequireTwoFactorForStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->role !== Role::User && ! $user->hasEnabledTwoFactorAuthentication()) {
            return redirect()->route('account')->with('status', 'two-factor-required');
        }

        return $next($request);
    }
}
