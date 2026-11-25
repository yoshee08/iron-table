{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Log entries, newest first, with both times (spec section 8.1). --}}
@use('App\Models\LogEntry')
@forelse ($logs as $entry)
    <div class="mb-2 rounded border border-zinc-200 bg-white p-2 text-sm dark:border-zinc-800 dark:bg-zinc-900">
        <p class="text-xs text-zinc-600 dark:text-zinc-400">
            <strong>{{ $entry->label() }}</strong> · simulated {{ $entry->sim_day > 1 ? 'day '.$entry->sim_day.' ' : '' }}{{ $entry->sim_time }} · real {{ $entry->created_at->format('H:i') }} · {{ $entry->recorder?->name ?? 'Deleted user' }}
        </p>
        <dl class="grid grid-cols-[auto_1fr] gap-x-3">
            @foreach ($entry->fields as $name => $value)
                @php($field = LogEntry::TYPES[$entry->type]['fields'][$name] ?? $name)
                <dt class="text-xs text-zinc-600 dark:text-zinc-400">{{ is_array($field) ? $field[0] : $field }}</dt>
                <dd class="whitespace-pre-line">{{ $value }}</dd>
            @endforeach
        </dl>
    </div>
@empty
    <p class="text-sm text-zinc-600 dark:text-zinc-400">No entries yet.</p>
@endforelse
