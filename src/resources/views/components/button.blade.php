{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<button {{ $attributes->merge(['type' => 'submit', 'class' => 'rounded bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-300']) }}>
    {{ $slot }}
</button>
