{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-layouts.app title="Correct a section">
    <form method="POST" action="{{ route('plans.sections.update', [$plan, $section]) }}" class="mb-8 max-w-2xl">
        @csrf
        @method('PUT')
        <x-field name="number" label="Number (for example 5.2; empty if the section has no number)" :value="$section->number" />
        <x-field name="title" label="Title" :value="$section->title" required />
        <div class="mb-4">
            <label for="body" class="mb-1 block text-sm font-medium">Text</label>
            <textarea id="body" name="body" rows="14" class="w-full rounded border border-zinc-300 bg-white px-3 py-2 font-mono text-sm dark:border-zinc-700 dark:bg-zinc-950">{{ old('body', $section->body) }}</textarea>
        </div>
        <x-button>Save</x-button>
        <a href="{{ route('plans.show', $plan) }}" class="ml-3 text-sm underline underline-offset-4">Cancel</a>
    </form>

    <details class="max-w-2xl">
        <summary class="cursor-pointer text-sm text-red-700 dark:text-red-400">Delete this section</summary>
        <p class="my-2 text-sm">Delete a section that is not a real section, for example a list item that the import read as a heading. Its text is lost.</p>
        <form method="POST" action="{{ route('plans.sections.destroy', [$plan, $section]) }}">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded bg-red-700 px-3 py-1 text-sm text-white hover:bg-red-600">Delete the section</button>
        </form>
    </details>
</x-layouts.app>
