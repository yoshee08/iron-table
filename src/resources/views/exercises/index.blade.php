{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-layouts.app title="Exercises">
    @if (session('status'))
        <p role="status" class="mb-4 rounded border border-green-300 bg-green-50 p-3 text-sm dark:border-green-700 dark:bg-green-950">{{ session('status') }}</p>
    @endif
    @can('moderate')
        <p class="mb-6"><a href="{{ route('exercises.create') }}" class="rounded bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900">Prepare an exercise</a></p>
    @endcan

    @if ($exercises->isEmpty())
        <p>There are no exercises for you yet.</p>
    @else
        <table class="w-full text-left text-sm">
            <thead class="border-b border-zinc-300 dark:border-zinc-700">
                <tr>
                    <th scope="col" class="py-2 pr-4">Exercise</th>
                    <th scope="col" class="py-2 pr-4">Status</th>
                    <th scope="col" class="py-2 pr-4">Open</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($exercises as $exercise)
                    <tr class="border-b border-zinc-200 dark:border-zinc-800">
                        <td class="py-2 pr-4">{{ $exercise->title }}</td>
                        <td class="py-2 pr-4">@include('exercises._status', ['status' => $exercise->status])</td>
                        <td class="space-x-3 py-2 pr-4">
                            <a href="{{ route('exercises.live', $exercise) }}" class="underline underline-offset-4">Live screen</a>
                            @can('moderate')
                                <a href="{{ route('exercises.control', $exercise) }}" class="underline underline-offset-4">Control</a>
                                <a href="{{ route('exercises.setup', $exercise) }}" class="underline underline-offset-4">Participants</a>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-layouts.app>
