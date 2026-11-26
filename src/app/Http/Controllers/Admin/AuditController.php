<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers\Admin;

use App\Models\AuditEvent;
use App\Services\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The audit log page for administrators (spec SEC-06): the newest events first,
 * and a check of the hash chain on request.
 */
class AuditController
{
    public function index(Request $request, AuditLog $audit): View
    {
        return view('admin.audit', [
            'events' => AuditEvent::query()->orderByDesc('id')->simplePaginate(50),
            'check' => $request->boolean('check') ? $audit->verify() : null,
        ]);
    }
}
