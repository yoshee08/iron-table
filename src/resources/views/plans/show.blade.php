{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-layouts.app :title="$plan->title">
    <p class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">
        Version {{ $plan->version }}{{ $isCurrent ? ' (current)' : '' }} · {{ $plan->file_name }} ·
        <a href="{{ route('plans.download', $plan) }}" class="underline underline-offset-4">Download the original</a> ·
        <a href="{{ route('plans.index') }}" class="underline underline-offset-4">All versions</a>
    </p>

    @if (session('status'))
        <p role="status" class="mb-4 rounded border border-green-300 bg-green-50 p-3 text-sm dark:border-green-700 dark:bg-green-950">{{ session('status') }}</p>
    @endif

    <form method="GET" action="{{ route('plans.show', $plan) }}" class="mb-6 flex max-w-md gap-2" role="search">
        <label for="q" class="sr-only">Search the plan</label>
        <input id="q" name="q" type="search" value="{{ $terms }}" placeholder="Search the plan, for example: severity" class="w-full rounded border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-950">
        <x-button>Search</x-button>
    </form>

    @if ($terms !== '')
        <p class="mb-4 text-sm">{{ $sections->count() }} sections match "{{ $terms }}". <a href="{{ route('plans.show', $plan) }}" class="underline underline-offset-4">Show all sections</a></p>
    @endif

    <ol class="space-y-2">
        @foreach ($sections as $section)
            <li class="rounded border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <details @if ($terms !== '') open @endif>
                    <summary class="cursor-pointer px-3 py-2 font-medium">{{ $section->number ? $section->number.' ' : '' }}{{ $section->title }}</summary>
                    <div class="border-t border-zinc-200 px-3 py-2 text-sm dark:border-zinc-800">
                        <p class="whitespace-pre-line">{{ $section->body }}</p>
                        @can('moderate')
                            <p class="mt-2"><a href="{{ route('plans.sections.edit', [$plan, $section]) }}" class="underline underline-offset-4">Correct this section</a></p>
                        @endcan
                    </div>
                </details>
            </li>
        @endforeach
    </ol>
</x-layouts.app>
