<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Middleware;

use App\Services\AuditLog;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records each change that a logged-in user makes, and each download of a plan,
 * a scenario, a report or an evidence package (spec SEC-06). The event has the
 * route name, the route parameters and the HTTP status, never the form input.
 * Logins, failed logins and password resets come from the auth events.
 */
class RecordAudit
{
    private const DOWNLOADS = [
        'plans.download',
        'scenarios.download',
        'exercises.report.pdf',
        'exercises.report.export',
    ];

    public function __construct(private AuditLog $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $response = $next($request);

        $route = $request->route();
        $name = is_object($route) ? $route->getName() : null;
        // Channel authorization for live updates is not a change.
        $change = ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true) && ! $request->is('broadcasting/*');

        if ($user !== null && $name !== null && ($change || in_array($name, self::DOWNLOADS, true))) {
            $data = [];
            foreach ($route->parameters() as $key => $value) {
                $data[$key] = $value instanceof Model ? $value->getKey() : mb_substr((string) $value, 0, 100);
            }
            $data['status'] = $response->getStatusCode();
            $this->audit->record($name, $data, $user);
        }

        return $response;
    }
}
