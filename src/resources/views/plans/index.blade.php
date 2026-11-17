{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-layouts.app title="IR plan">
    @can('moderate')
        <p class="mb-6"><a href="{{ route('plans.create') }}" class="rounded bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900">Upload a plan</a></p>
    @endcan

    @if ($plans->isEmpty())
        <p>No IR plan is uploaded yet.@can('moderate') Upload the organization's plan as a PDF, DOCX or Markdown file.@endcan</p>
    @else
        <table class="w-full text-left text-sm">
            <thead class="border-b border-zinc-300 dark:border-zinc-700">
                <tr>
                    <th scope="col" class="py-2 pr-4">Version</th>
                    <th scope="col" class="py-2 pr-4">Title</th>
                    <th scope="col" class="py-2 pr-4">Sections</th>
                    <th scope="col" class="py-2 pr-4">Uploaded</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($plans as $plan)
                    <tr class="border-b border-zinc-200 dark:border-zinc-800">
                        <td class="py-2 pr-4">{{ $plan->version }}@if ($loop->first) (current)@endif</td>
                        <td class="py-2 pr-4"><a href="{{ route('plans.show', $plan) }}" class="underline underline-offset-4">{{ $plan->title }}</a></td>
                        <td class="py-2 pr-4">{{ $plan->sections_count }}</td>
                        <td class="py-2 pr-4">{{ $plan->created_at->toDateString() }}@if ($plan->uploader) by {{ $plan->uploader->name }}@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-layouts.app>
