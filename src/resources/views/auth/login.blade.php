{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-layouts.guest title="Log in">
    <form method="POST" action="{{ route('login.store') }}">
        @csrf
        <x-field name="email" label="Email" type="email" autocomplete="username" required autofocus />
        <x-field name="password" label="Password" type="password" autocomplete="current-password" required />
        <label class="mb-4 flex items-center gap-2 text-sm">
            <input type="checkbox" name="remember" value="1">
            Keep me logged in
        </label>
        <x-button class="w-full">Log in</x-button>
    </form>
    <p class="mt-4 text-center text-sm">
        <a href="{{ route('password.request') }}" class="underline underline-offset-4">Forgot your password?</a>
    </p>
    <p class="mt-2 text-center text-sm">
        <a href="{{ route('join') }}" class="underline underline-offset-4">Join an exercise with a session code</a>
    </p>
</x-layouts.guest>
