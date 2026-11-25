{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- The after-action report (spec section 8.4). Used on the screen and in the PDF, so plain HTML. $aar: from App\Services\AarBuilder. --}}
<p class="tlp"><strong>{{ $aar['tlp'] }}</strong></p>
<h1>After-action report: {{ $aar['title'] }}</h1>
<p>Report version {{ $aar['version'] }}</p>

<h2>1. Exercise information</h2>
<table>
    @foreach ($aar['information'] as $label => $value)
        <tr><th>{{ $label }}</th><td>{{ $value }}</td></tr>
    @endforeach
</table>
<table>
    <tr><th>Participant</th><th>Exercise role</th><th>Plan role</th></tr>
    @forelse ($aar['participants'] as $participant)
        <tr><td>{{ $participant['name'] }}</td><td>{{ $participant['role'] }}</td><td>{{ $participant['plan_role'] }}</td></tr>
    @empty
        <tr><td colspan="3">No participants.</td></tr>
    @endforelse
</table>

<h2>2. Scenario summary</h2>
<p class="text">{{ $aar['summary'] }}</p>

<h2>3. Phase review</h2>
@forelse ($aar['phases'] as $phase)
    <h3>{{ $phase['phase'] }}: {{ $phase['earned'] }} of {{ $phase['possible'] }} points</h3>
    <p class="text">{{ $phase['text'] ?: 'No review written.' }}</p>
@empty
    <p>The scenario has no rubric.</p>
@endforelse
<p><strong>Total score: {{ $aar['score']['percent'] === null ? 'no rubric' : $aar['score']['percent'].'%' }}</strong> ({{ $aar['score']['earned'] }} of {{ $aar['score']['possible'] }} points)</p>
@if ($aar['changed_scores'])
    <p>Changed scores:</p>
    <ul>
        @foreach ($aar['changed_scores'] as $change)
            <li>{{ $change['item'] }}: {{ $change['points'] }} points. Reason: {{ $change['reason'] }}</li>
        @endforeach
    </ul>
@endif
@if ($aar['objectives'])
    <table>
        <tr><th>Objective</th><th>Rating (HSEEP)</th><th>Justification</th></tr>
        @foreach ($aar['objectives'] as $objective)
            <tr><td>{{ $objective['objective'] }}</td><td>{{ $objective['rating'] }}</td><td>{{ $objective['justification'] }}</td></tr>
        @endforeach
    </table>
@endif

<h2>4. Plan coverage</h2>
<table>
    @foreach (['Used', 'Missed', 'Extra'] as $key)
        <tr><th>{{ $key }}</th><td>{{ implode('; ', $aar['coverage'][$key]) ?: 'None' }}</td></tr>
    @endforeach
    <tr><th>Plan gaps</th><td>{{ $aar['coverage']['gaps'] }} responses without plan coverage</td></tr>
</table>

<h2>5. Strengths and weaknesses</h2>
<p><strong>Strengths</strong></p>
<ul>@forelse ($aar['strengths'] as $text)<li>{{ $text }}</li>@empty<li>None recorded.</li>@endforelse</ul>
<p><strong>Weaknesses and plan gaps</strong></p>
<ul>@forelse ($aar['weaknesses'] as $text)<li>{{ $text }}</li>@empty<li>None recorded.</li>@endforelse</ul>

<h2>6. Corrective actions</h2>
<table>
    <tr><th>Control or process</th><th>Action</th><th>Owner</th><th>Due</th><th>Documents to update</th><th>Status</th></tr>
    @forelse ($aar['actions'] as $action)
        <tr><td>{{ $action['control'] }}</td><td>{{ $action['action'] }}</td><td>{{ $action['owner'] }}</td><td>{{ $action['due'] }}</td><td>{{ $action['documents'] }}</td><td>{{ $action['status'] }}</td></tr>
    @empty
        <tr><td colspan="6">No corrective actions.</td></tr>
    @endforelse
</table>

<h2>7. Closure</h2>
<table>
    <tr><th>Approved by</th><td>{{ $aar['closure']['approver'] ?? 'Not approved (draft)' }}</td></tr>
    <tr><th>Approval date</th><td>{{ $aar['closure']['date'] ?? '' }}</td></tr>
    <tr><th>Final score</th><td>{{ $aar['closure']['score'] === null ? 'No rubric' : $aar['closure']['score'].'%' }}</td></tr>
</table>
