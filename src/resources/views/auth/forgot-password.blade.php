{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-layouts.guest title="Reset your password">
    <p class="mb-4 text-sm">Type your email address. If an account exists, you get an email with a password reset link.</p>
    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <x-field name="email" label="Email" type="email" autocomplete="username" required autofocus />
        <x-button class="w-full">Send the reset link</x-button>
    </form>
    <p class="mt-4 text-center text-sm">
        <a href="{{ route('login') }}" class="underline underline-offset-4">Back to log in</a>
    </p>
</x-layouts.guest>
