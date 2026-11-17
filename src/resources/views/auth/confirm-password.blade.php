{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-layouts.guest title="Confirm your password">
    <p class="mb-4 text-sm">This is a security setting. Type your password to continue.</p>
    <form method="POST" action="{{ route('password.confirm.store') }}">
        @csrf
        <x-field name="password" label="Password" type="password" autocomplete="current-password" required autofocus />
        <x-button class="w-full">Confirm</x-button>
    </form>
</x-layouts.guest>
