{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- A labelled form input with its validation message. Password values are never put back into the form. --}}
@props(['name', 'label', 'type' => 'text', 'value' => null, 'bag' => 'default'])
<div class="mb-4">
    <label for="{{ $name }}" class="mb-1 block text-sm font-medium">{{ $label }}</label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ $type === 'password' ? '' : old($name, $value) }}"
        @error($name, $bag) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
        {{ $attributes->merge(['class' => 'w-full rounded border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-950']) }}
    >
    @error($name, $bag)
        <p id="{{ $name }}-error" class="mt-1 text-sm text-red-700 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
