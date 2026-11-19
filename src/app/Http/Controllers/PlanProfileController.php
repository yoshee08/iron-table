<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers;

use App\Models\IrPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * The organization profile of an IR plan version (spec section 5.2). Moderators
 * type it as YAML. Scenarios use the values as {{org.*}} placeholders.
 */
class PlanProfileController
{
    private const TEMPLATE = <<<'YAML'
        # Organization profile. Scenarios use these values as placeholders,
        # for example {{org.name}} or {{org.role.ciso.name}}.
        # Type a value after each colon. Add more names if a scenario needs them.
        name:
        domain:
          example_user:
        role:
          ciso:
            name:
            contact:
          incident_lead:
            name:
            contact:
        channel:
          report:
        severity:
          low: Low
          medium: Medium
          high: High

        YAML;

    public function edit(IrPlan $plan): View
    {
        return view('plans.profile', ['plan' => $plan, 'profile' => $plan->profile ?? self::TEMPLATE]);
    }

    public function update(Request $request, IrPlan $plan): RedirectResponse
    {
        $profile = (string) ($request->validate(['profile' => ['nullable', 'string', 'max:65535']])['profile'] ?? '');
        $profile = str_replace("\r\n", "\n", $profile);

        try {
            $data = Yaml::parse($profile);
        } catch (ParseException $e) {
            return back()->withInput()->withErrors(['profile' => 'The profile is not valid YAML: '.$e->getMessage()]);
        }
        if ($data !== null && ! is_array($data)) {
            return back()->withInput()->withErrors(['profile' => 'Type one name and value on each line, for example "name: Example Corp".']);
        }

        $plan->forceFill(['profile' => $profile])->save();

        return redirect()->route('plans.show', $plan)->with('status', 'Organization profile saved.');
    }
}
