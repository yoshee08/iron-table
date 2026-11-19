{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-layouts.guest title="Two-factor authentication">
    <p class="mb-4 text-sm">Type the code from your authenticator app. If you do not have your app, type one of your recovery codes.</p>
    <form method="POST" action="{{ route('two-factor.login.store') }}">
        @csrf
        <x-field name="code" label="Code from the app" inputmode="numeric" autocomplete="one-time-code" autofocus />
        <x-field name="recovery_code" label="Or a recovery code" autocomplete="off" />
        <x-button class="w-full">Continue</x-button>
    </form>
</x-layouts.guest>
