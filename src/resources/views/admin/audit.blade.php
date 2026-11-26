{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-layouts.app title="Audit log">
    <p class="mb-4 max-w-3xl text-sm">
        The audit log records logins, failed logins, password resets, each change and each download of a plan,
        a scenario, a report or an evidence package. Nobody can change or delete an event. Each event has the
        hash of the event before it, so a change in the database breaks the chain.
    </p>

    @if ($check)
        @if ($check['broken'] === null)
            <p role="status" class="mb-4 rounded border border-green-300 bg-green-50 p-3 text-sm dark:border-green-700 dark:bg-green-950">
                Events checked: {{ $check['count'] }}. The chain is complete.
            </p>
        @else
            <p role="alert" class="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm dark:border-red-700 dark:bg-red-950">
                The chain breaks at event {{ $check['broken'] }}. Somebody changed or deleted an event in the database.
            </p>
        @endif
    @endif

    <form method="GET" action="{{ route('admin.audit.index') }}" class="mb-6">
        <input type="hidden" name="check" value="1">
        <x-button>Check the chain</x-button>
    </form>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-zinc-300 dark:border-zinc-700">
                <tr>
                    <th scope="col" class="py-2 pr-4">ID</th>
                    <th scope="col" class="py-2 pr-4">Time (UTC)</th>
                    <th scope="col" class="py-2 pr-4">Who</th>
                    <th scope="col" class="py-2 pr-4">Action</th>
                    <th scope="col" class="py-2 pr-4">Details</th>
                    <th scope="col" class="py-2">IP address</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($events as $event)
                    <tr class="border-b border-zinc-200 align-top dark:border-zinc-800">
                        <td class="py-2 pr-4">{{ $event->id }}</td>
                        <td class="py-2 pr-4 whitespace-nowrap">{{ substr($event->created_at, 0, 19) }}</td>
                        <td class="py-2 pr-4">{{ $event->actor }}</td>
                        <td class="py-2 pr-4 font-mono text-xs">{{ $event->action }}</td>
                        <td class="py-2 pr-4 font-mono text-xs">
                            @foreach ($event->details() as $key => $value)
                                {{ $key }}={{ is_scalar($value) ? $value : json_encode($value) }}@if (! $loop->last), @endif
                            @endforeach
                        </td>
                        <td class="py-2">{{ $event->ip }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-2">No events yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <nav class="mt-4 flex gap-4 text-sm" aria-label="Pages">
        @if ($events->previousPageUrl())
            <a href="{{ $events->previousPageUrl() }}" class="underline underline-offset-4">Newer events</a>
        @endif
        @if ($events->nextPageUrl())
            <a href="{{ $events->nextPageUrl() }}" class="underline underline-offset-4">Older events</a>
        @endif
    </nav>
</x-layouts.app>
