{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Status with a text label: color is never the only sign (spec section 7.7). --}}
@php
    $labels = ['prepared' => 'Prepared', 'running' => 'Running', 'paused' => 'Paused', 'stopped' => 'STOPPED', 'ended' => 'Ended'];
    $colors = ['running' => 'bg-green-100 text-green-900', 'paused' => 'bg-amber-100 text-amber-900', 'stopped' => 'bg-red-700 text-white', 'ended' => 'bg-zinc-200 text-zinc-800'];
@endphp
<span class="rounded px-2 py-0.5 text-xs font-semibold {{ $colors[$status] ?? 'bg-zinc-100 text-zinc-800' }}">{{ $labels[$status] ?? $status }}</span>
