<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers;

use App\Models\IrPlan;
use App\Models\PlanSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Moderators correct the sections that the import found (spec section 5.1, step 3).
 */
class PlanSectionController
{
    public function edit(IrPlan $plan, PlanSection $section): View
    {
        return view('plans.section-edit', ['plan' => $plan, 'section' => $section]);
    }

    public function update(Request $request, IrPlan $plan, PlanSection $section): RedirectResponse
    {
        $data = $request->validate([
            'number' => ['nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)*$/'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
        ]);

        $section->update([
            'number' => $data['number'] ?? null,
            'title' => $data['title'],
            'body' => $data['body'] ?? '',
        ]);

        return redirect()->route('plans.show', $plan)->with('status', 'Section saved.');
    }

    public function destroy(IrPlan $plan, PlanSection $section): RedirectResponse
    {
        $section->delete();

        return redirect()->route('plans.show', $plan)->with('status', 'Section deleted.');
    }
}
