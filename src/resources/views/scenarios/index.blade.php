{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-layouts.app title="Scenarios">
    @if (session('status'))
        <p role="status" class="mb-4 rounded border border-green-300 bg-green-50 p-3 text-sm dark:border-green-700 dark:bg-green-950">{{ session('status') }}</p>
    @endif

    <p class="mb-6"><a href="{{ route('scenarios.create') }}" class="rounded bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900">Import a scenario</a></p>

    @if ($scenarios->isEmpty())
        <p>No scenario is imported yet. Import a scenario YAML file, or start with the <a href="{{ route('scenarios.example') }}" class="underline underline-offset-4">example scenario</a>.</p>
    @else
        <table class="w-full text-left text-sm">
            <thead class="border-b border-zinc-300 dark:border-zinc-700">
                <tr>
                    <th scope="col" class="py-2 pr-4">Title</th>
                    <th scope="col" class="py-2 pr-4">ID</th>
                    <th scope="col" class="py-2 pr-4">Version</th>
                    <th scope="col" class="py-2 pr-4">Imported</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($scenarios as $scenario)
                    <tr class="border-b border-zinc-200 dark:border-zinc-800">
                        <td class="py-2 pr-4"><a href="{{ route('scenarios.show', $scenario) }}" class="underline underline-offset-4">{{ $scenario->title }}</a></td>
                        <td class="py-2 pr-4 font-mono text-xs">{{ $scenario->slug }}</td>
                        <td class="py-2 pr-4">{{ $scenario->version }}</td>
                        <td class="py-2 pr-4">{{ $scenario->created_at->toDateString() }}@if ($scenario->uploader) by {{ $scenario->uploader->name }}@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-layouts.app>
