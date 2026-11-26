{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-layouts.guest title="Join an exercise">
    <p class="mb-4 text-sm">Type the session code from the moderator and your name. The other participants see your name.</p>
    <form method="POST" action="{{ route('join.store') }}">
        @csrf
        <x-field name="code" label="Session code" :value="$code" autocomplete="off" autocapitalize="characters" spellcheck="false" required autofocus />
        <x-field name="name" label="Your name" autocomplete="name" required />
        <x-button class="w-full">Join</x-button>
    </form>
    <p class="mt-4 text-center text-sm">
        <a href="{{ route('login') }}" class="underline underline-offset-4">Log in with an account</a>
    </p>
</x-layouts.guest>
