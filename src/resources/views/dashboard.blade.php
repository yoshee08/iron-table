{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-layouts.app title="Dashboard">
    <p>You are logged in as {{ auth()->user()->email }}.</p>
    @unless (auth()->user()->hasEnabledTwoFactorAuthentication())
        <p class="mt-4 rounded border border-amber-300 bg-amber-50 p-3 text-sm dark:border-amber-700 dark:bg-amber-950">
            Two-factor authentication is off. Turn it on in <a href="{{ route('account') }}" class="underline underline-offset-4">Account</a>.
        </p>
    @endunless
</x-layouts.app>
