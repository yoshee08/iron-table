{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-layouts.app title="Prepare an exercise">
    <form method="POST" action="{{ route('exercises.store') }}" class="max-w-md">
        @csrf
        <p class="mb-4 text-sm">The exercise keeps a copy of the scenario and the current IR plan version. Later changes to the scenario do not change this exercise. The scenario must pass the consistency check.</p>
        <div class="mb-4">
            <label for="scenario_id" class="mb-1 block text-sm font-medium">Scenario</label>
            <select id="scenario_id" name="scenario_id" required
                @error('scenario_id') aria-invalid="true" aria-describedby="scenario_id-error" @enderror
                class="w-full rounded border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-950">
                @foreach ($scenarios as $scenario)
                    <option value="{{ $scenario->id }}" @selected((int) old('scenario_id') === $scenario->id)>{{ $scenario->title }} (version {{ $scenario->version }})</option>
                @endforeach
            </select>
            @error('scenario_id')
                <p id="scenario_id-error" class="mt-1 text-sm text-red-700 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
        <x-field name="title" label="Title (optional, for example: Q1 2026 tabletop)" />
        <x-button>Prepare the exercise</x-button>
    </form>
</x-layouts.app>
