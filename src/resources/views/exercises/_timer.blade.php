{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Inject timer (backlog B10), in real minutes. resources/js/live.js counts down. --}}
@if ($release->deadline())
    <p class="mb-2 inline-block rounded bg-amber-100 px-2 py-1 text-sm font-semibold text-amber-900">
        Timer ({{ $release->timer_minutes }} min): <span data-deadline="{{ $release->deadline()->getTimestampMs() }}">{{ $release->deadline()->isPast() ? 'Time is up' : $release->deadline()->diff(now())->format('%I:%S').' left' }}</span>
    </p>
@endif
