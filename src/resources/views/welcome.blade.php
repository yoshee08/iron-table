{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }}</title>
        @include('partials.reverb-meta')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-screen items-center justify-center bg-white p-6 text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100">
        <main class="max-w-md text-center">
            <h1 class="text-2xl font-semibold">{{ config('app.name') }}</h1>
            <p class="mt-2 text-zinc-600 dark:text-zinc-400">Incident response tabletop exercises.</p>
        </main>
    </body>
</html>
