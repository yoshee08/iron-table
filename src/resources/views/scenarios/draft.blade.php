{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-layouts.app title="Draft a scenario with AI">
    <p class="mb-4 rounded border border-amber-300 bg-amber-50 p-3 text-sm dark:border-amber-700 dark:bg-amber-950">{{ $notice }}</p>

    <form method="POST" action="{{ route('scenarios.draft.store') }}" class="max-w-md">
        @csrf
        <p class="mb-4 text-sm">The AI writes the outline and the facts first. Then you write each inject, artifact and rubric with the AI buttons on the scenario page, one at a time. A small local model can take up to a minute for each step. Check every part: the AI makes mistakes.</p>
        <x-field name="incident" label="Incident type (for example: ransomware on a file server, phishing, lost laptop)" required />
        <div class="mb-4">
            <label for="difficulty" class="mb-1 block text-sm font-medium">Difficulty</label>
            <select id="difficulty" name="difficulty" class="w-full rounded border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-950">
                @foreach (['beginner', 'intermediate', 'advanced'] as $level)
                    <option value="{{ $level }}" @selected(old('difficulty', 'beginner') === $level)>{{ ucfirst($level) }}</option>
                @endforeach
            </select>
        </div>
        <x-field name="injects" label="Number of injects (2 to 6)" type="number" min="2" max="6" :value="4" required />
        <x-button>Write the outline and the facts</x-button>
    </form>
</x-layouts.app>
