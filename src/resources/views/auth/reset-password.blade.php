{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-layouts.guest title="Set a new password">
    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-field name="email" label="Email" type="email" :value="$request->email" autocomplete="username" required />
        <x-field name="password" label="New password (at least 12 characters)" type="password" autocomplete="new-password" required autofocus />
        <x-field name="password_confirmation" label="New password again" type="password" autocomplete="new-password" required />
        <x-button class="w-full">Set the password</x-button>
    </form>
</x-layouts.guest>
