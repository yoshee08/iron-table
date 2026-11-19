{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- The two clocks (spec section 8.1). resources/js/live.js counts the real time since the start. --}}
<span class="whitespace-nowrap">Simulated: <strong>{{ $exercise->simClock() }}</strong></span>
@if ($exercise->started_at)
    <span class="whitespace-nowrap">· Real: <strong data-since="{{ $exercise->started_at->getTimestampMs() }}" @if ($exercise->ended_at) data-until="{{ $exercise->ended_at->getTimestampMs() }}" @endif>{{ $exercise->started_at->diff($exercise->ended_at ?? now())->format('%H:%I:%S') }}</strong> since start</span>
@endif
