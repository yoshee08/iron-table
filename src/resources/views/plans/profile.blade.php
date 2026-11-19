{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-layouts.app title="Organization profile">
    <form method="POST" action="{{ route('plans.profile.update', $plan) }}" class="max-w-2xl">
        @csrf
        @method('PUT')
        <p class="mb-4 text-sm">IR plan version {{ $plan->version }}. Type the values from the plan. Scenarios use them as placeholders: for example, <code>role: ciso: name:</code> fills <code>@{{org.role.ciso.name}}</code>. A new plan version starts with a copy of this profile.</p>
        <label for="profile" class="mb-1 block text-sm font-medium">Profile (YAML)</label>
        <textarea id="profile" name="profile" rows="24" spellcheck="false"
            @error('profile') aria-invalid="true" aria-describedby="profile-error" @enderror
            class="w-full rounded border border-zinc-300 bg-white px-3 py-2 font-mono text-sm dark:border-zinc-700 dark:bg-zinc-950">{{ old('profile', $profile) }}</textarea>
        @error('profile')
            <p id="profile-error" class="mt-1 text-sm text-red-700 dark:text-red-400">{{ $message }}</p>
        @enderror
        <div class="mt-4">
            <x-button>Save</x-button>
            <a href="{{ route('plans.show', $plan) }}" class="ml-3 text-sm underline underline-offset-4">Cancel</a>
        </div>
    </form>
</x-layouts.app>
