{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
@props(['title'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }} - {{ config('app.name') }}</title>
        {{-- Read by Laravel Echo to authorize private live-update channels. --}}
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @include('partials.reverb-meta')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100">
        <header class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <nav class="mx-auto flex max-w-4xl flex-wrap items-center gap-4 px-4 py-3" aria-label="Main">
                <a href="{{ route('dashboard') }}" class="font-semibold">{{ config('app.name') }}</a>
                <a href="{{ route('dashboard') }}" class="text-sm underline-offset-4 hover:underline">Dashboard</a>
                <a href="{{ route('exercises.index') }}" class="text-sm underline-offset-4 hover:underline">Exercises</a>
                <a href="{{ route('plans.index') }}" class="text-sm underline-offset-4 hover:underline">IR plan</a>
                @can('moderate')
                    <a href="{{ route('scenarios.index') }}" class="text-sm underline-offset-4 hover:underline">Scenarios</a>
                @endcan
                @can('admin')
                    <a href="{{ route('admin.users.index') }}" class="text-sm underline-offset-4 hover:underline">Users</a>
                @endcan
                <a href="{{ route('account') }}" class="text-sm underline-offset-4 hover:underline">Account</a>
                <span class="ml-auto text-sm text-zinc-600 dark:text-zinc-400">{{ auth()->user()->name }} ({{ auth()->user()->role->label() }})</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm underline-offset-4 hover:underline">Log out</button>
                </form>
            </nav>
        </header>
        <main class="mx-auto max-w-4xl px-4 py-8">
            <h1 class="mb-6 text-2xl font-semibold">{{ $title }}</h1>
            {{ $slot }}
        </main>
    </body>
</html>
