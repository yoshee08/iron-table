{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Forms for log entries (spec section 7.3). The real and simulated times are filled automatically. $types: the entry types to show. --}}
@use('App\Models\LogEntry')
@error('log')
    <p role="alert" class="mb-2 text-sm text-red-700 dark:text-red-400">{{ $message }}</p>
@enderror
@foreach ($types as $type)
    <details id="log-form-{{ $type }}" class="mb-2 rounded border border-zinc-200 bg-white text-sm dark:border-zinc-800 dark:bg-zinc-900">
        <summary class="cursor-pointer px-3 py-2 font-medium">{{ LogEntry::TYPES[$type]['label'] }}: add an entry</summary>
        <form method="POST" action="{{ route('exercises.logs.store', [$exercise, $type]) }}" class="grid gap-2 border-t border-zinc-200 p-3 sm:grid-cols-2 dark:border-zinc-800">
            @csrf
            @foreach (LogEntry::TYPES[$type]['fields'] as $name => $field)
                <div class="{{ in_array($name, ['summary', 'action', 'result', 'notes', 'topic', 'note'], true) ? 'sm:col-span-2' : '' }}">
                    <label for="log-{{ $type }}-{{ $name }}" class="mb-1 block text-xs font-medium">{{ is_array($field) ? $field[0] : $field }}</label>
                    @if (is_array($field))
                        <select id="log-{{ $type }}-{{ $name }}" name="{{ $name }}" data-keep class="w-full rounded border border-zinc-300 bg-white px-2 py-1 dark:border-zinc-700 dark:bg-zinc-950">
                            <option value="">—</option>
                            @foreach ($field[1] as $value)
                                <option value="{{ $value }}">{{ ucfirst($value) }}</option>
                            @endforeach
                        </select>
                    @elseif (in_array($name, ['summary', 'action', 'result', 'notes', 'topic', 'note'], true))
                        <textarea id="log-{{ $type }}-{{ $name }}" name="{{ $name }}" rows="2" data-keep class="w-full rounded border border-zinc-300 bg-white px-2 py-1 dark:border-zinc-700 dark:bg-zinc-950"></textarea>
                    @else
                        <input id="log-{{ $type }}-{{ $name }}" name="{{ $name }}" data-keep class="w-full rounded border border-zinc-300 bg-white px-2 py-1 dark:border-zinc-700 dark:bg-zinc-950">
                    @endif
                </div>
            @endforeach
            <div class="sm:col-span-2"><x-button>Save the entry</x-button></div>
        </form>
    </details>
@endforeach
