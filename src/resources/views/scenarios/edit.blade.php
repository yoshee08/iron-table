{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-layouts.app :title="'Edit: '.$scenario->title">
    <form method="POST" action="{{ route('scenarios.update', $scenario) }}">
        @csrf
        @method('PUT')
        <label for="source" class="mb-1 block text-sm font-medium">Scenario (YAML)</label>
        <textarea id="source" name="source" rows="30" spellcheck="false"
            @error('source') aria-invalid="true" aria-describedby="source-error" @enderror
            class="w-full rounded border border-zinc-300 bg-white px-3 py-2 font-mono text-sm dark:border-zinc-700 dark:bg-zinc-950">{{ old('source', $scenario->source) }}</textarea>
        @error('source')
            <p id="source-error" class="mt-1 text-sm text-red-700 dark:text-red-400">{{ $message }}</p>
        @enderror
        <div class="mt-4">
            <x-button>Save</x-button>
            <a href="{{ route('scenarios.show', $scenario) }}" class="ml-3 text-sm underline underline-offset-4">Cancel</a>
        </div>
    </form>
</x-layouts.app>
