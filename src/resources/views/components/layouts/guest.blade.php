{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@props(['title'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }} - {{ config('app.name') }}</title>
        @vite(['resources/css/app.css'])
    </head>
    <body class="flex min-h-screen items-center justify-center bg-zinc-50 p-4 text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100">
        <main class="w-full max-w-sm">
            <p class="mb-6 text-center text-xl font-semibold">{{ config('app.name') }}</p>
            <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
                <h1 class="mb-4 text-lg font-medium">{{ $title }}</h1>
                @if (session('status'))
                    <p role="status" class="mb-4 text-sm text-green-700 dark:text-green-400">{{ session('status') }}</p>
                @endif
                {{ $slot }}
            </div>
        </main>
    </body>
</html>
