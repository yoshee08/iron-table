{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@php
    $user = auth()->user();
    $messages = [
        'password-updated' => 'Your password is changed.',
        'two-factor-authentication-enabled' => 'Scan the QR code, then type a code to finish.',
        'two-factor-authentication-confirmed' => 'Two-factor authentication is on.',
        'two-factor-authentication-disabled' => 'Two-factor authentication is off.',
        'recovery-codes-generated' => 'New recovery codes are made. The old codes do not work now.',
    ];
@endphp
<x-layouts.app title="Account">
    @if (isset($messages[session('status')]))
        <p role="status" class="mb-6 rounded border border-green-300 bg-green-50 p-3 text-sm dark:border-green-700 dark:bg-green-950">{{ $messages[session('status')] }}</p>
    @endif

    <section class="mb-10 max-w-md" aria-labelledby="password-heading">
        <h2 id="password-heading" class="mb-4 text-lg font-medium">Change your password</h2>
        <form method="POST" action="{{ route('user-password.update') }}">
            @csrf
            @method('PUT')
            <x-field name="current_password" label="Current password" type="password" autocomplete="current-password" bag="updatePassword" required />
            <x-field name="password" label="New password (at least 12 characters)" type="password" autocomplete="new-password" bag="updatePassword" required />
            <x-field name="password_confirmation" label="New password again" type="password" autocomplete="new-password" bag="updatePassword" required />
            <x-button>Change the password</x-button>
        </form>
    </section>

    <section class="max-w-md" aria-labelledby="two-factor-heading">
        <h2 id="two-factor-heading" class="mb-4 text-lg font-medium">Two-factor authentication</h2>

        @if (! $user->two_factor_secret)
            <p class="mb-4 text-sm">Two-factor authentication is off. Turn it on to protect your account with a code from an authenticator app.</p>
            <form method="POST" action="{{ route('two-factor.enable') }}">
                @csrf
                <x-button>Turn on</x-button>
            </form>
        @elseif (! $user->two_factor_confirmed_at)
            <p class="mb-4 text-sm">1. Scan this QR code with your authenticator app.</p>
            <div class="mb-4 inline-block bg-white p-2">{!! $user->twoFactorQrCodeSvg() !!}</div>
            <p class="mb-4 text-sm">2. Type the code that the app shows.</p>
            <form method="POST" action="{{ route('two-factor.confirm') }}">
                @csrf
                <x-field name="code" label="Code from the app" inputmode="numeric" autocomplete="one-time-code" bag="confirmTwoFactorAuthentication" required />
                <x-button>Finish</x-button>
            </form>
        @else
            <p class="mb-4 text-sm">Two-factor authentication is on.</p>
            <p class="mb-2 text-sm font-medium">Recovery codes</p>
            <p class="mb-2 text-sm">Keep these codes in a safe place. Each code works one time, if you do not have your app.</p>
            <ul class="mb-4 rounded bg-zinc-100 p-3 font-mono text-sm dark:bg-zinc-900">
                @foreach ($user->recoveryCodes() as $code)
                    <li>{{ $code }}</li>
                @endforeach
            </ul>
            <div class="flex flex-wrap gap-3">
                <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}">
                    @csrf
                    <x-button>Make new recovery codes</x-button>
                </form>
                <form method="POST" action="{{ route('two-factor.disable') }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded bg-red-700 px-4 py-2 text-sm font-medium text-white hover:bg-red-600">Turn off</button>
                </form>
            </div>
        @endif
    </section>
</x-layouts.app>
