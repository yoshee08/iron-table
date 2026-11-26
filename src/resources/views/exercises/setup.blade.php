{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-layouts.app :title="'Participants: '.$exercise->title">
    <p class="mb-4 text-sm">
        @include('exercises._status', ['status' => $exercise->status]) ·
        <a href="{{ route('exercises.control', $exercise) }}" class="underline underline-offset-4">Control</a> ·
        <a href="{{ route('exercises.live', $exercise) }}" class="underline underline-offset-4">Live screen</a>
    </p>
    @if (session('status'))
        <p role="status" class="mb-4 rounded border border-green-300 bg-green-50 p-3 text-sm dark:border-green-700 dark:bg-green-950">{{ session('status') }}</p>
    @endif

    <table class="mb-8 w-full text-left text-sm">
        <thead class="border-b border-zinc-300 dark:border-zinc-700">
            <tr>
                <th scope="col" class="py-2 pr-4">Name</th>
                <th scope="col" class="py-2 pr-4">Exercise role</th>
                <th scope="col" class="py-2 pr-4">Plan role</th>
                <th scope="col" class="py-2 pr-4"><span class="sr-only">Remove</span></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($participants as $participant)
                <tr class="border-b border-zinc-200 dark:border-zinc-800">
                    <td class="py-2 pr-4">{{ $participant->user->name }}@if ($participant->user->isGuest()) <span class="text-zinc-600 dark:text-zinc-400">(guest)</span>@endif</td>
                    <td class="py-2 pr-4">{{ $participant->role->label() }}</td>
                    <td class="py-2 pr-4">
                        <form method="POST" action="{{ route('exercises.participants.update', [$exercise, $participant]) }}" class="flex gap-2">
                            @csrf
                            @method('PUT')
                            <input type="text" name="plan_role" value="{{ $participant->plan_role }}" maxlength="100" aria-label="Plan role of {{ $participant->user->name }}" class="w-40 rounded border border-zinc-300 bg-white px-2 py-1 dark:border-zinc-700 dark:bg-zinc-950">
                            <button type="submit" class="text-sm underline underline-offset-4">Save</button>
                        </form>
                    </td>
                    <td class="py-2 pr-4">
                        <form method="POST" action="{{ route('exercises.participants.destroy', [$exercise, $participant]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm underline underline-offset-4">Remove</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="py-2">No participants yet.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2 class="mb-2 text-lg font-semibold">Guest access</h2>
    <div class="mb-8 max-w-xl text-sm">
        @error('guests') <p role="alert" class="mb-2 text-red-700 dark:text-red-400">{{ $message }}</p> @enderror
        @if ($exercise->guest_code)
            <p class="mb-2">Guests join without an account at <a href="{{ route('join', ['code' => $exercise->guest_code]) }}" class="underline underline-offset-4">{{ route('join') }}</a> with this session code:</p>
            <p class="mb-2 font-mono text-2xl tracking-widest">{{ $exercise->guest_code }}</p>
            <p class="mb-2">A guest is a participant of this exercise only. The code stops working when the session ends or when you turn guest access off.</p>
            <form method="POST" action="{{ route('exercises.guests', [$exercise, 'off']) }}">
                @csrf
                <button type="submit" class="underline underline-offset-4">Turn guest access off</button>
            </form>
        @elseif ($exercise->status !== 'ended')
            <p class="mb-2">Guest access is off. Turn it on to get a session code for people without an account.</p>
            <form method="POST" action="{{ route('exercises.guests', [$exercise, 'on']) }}">
                @csrf
                <x-button>Turn guest access on</x-button>
            </form>
        @else
            <p>The session has ended. Guests cannot join.</p>
        @endif
    </div>

    <h2 class="mb-2 text-lg font-semibold">Add a participant</h2>
    @if ($users->isEmpty())
        <p class="text-sm">All users are in this exercise. Add users in <strong>Users</strong>.</p>
    @else
        <form method="POST" action="{{ route('exercises.participants.store', $exercise) }}" class="max-w-md">
            @csrf
            <div class="mb-4">
                <label for="user_id" class="mb-1 block text-sm font-medium">User</label>
                <select id="user_id" name="user_id" class="w-full rounded border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-950">
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                    @endforeach
                </select>
                @error('user_id') <p class="mt-1 text-sm text-red-700 dark:text-red-400">{{ $message }}</p> @enderror
            </div>
            <div class="mb-4">
                <label for="role" class="mb-1 block text-sm font-medium">Exercise role</label>
                <select id="role" name="role" class="w-full rounded border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-950">
                    @foreach (\App\Enums\ExerciseRole::cases() as $role)
                        <option value="{{ $role->value }}">{{ $role->label() }}</option>
                    @endforeach
                </select>
            </div>
            <x-field name="plan_role" label="Plan role (optional, for example: CISO, Incident lead, MSP contact)" />
            <x-button>Add</x-button>
        </form>
    @endif

    <details class="mt-8">
        <summary class="cursor-pointer text-sm text-red-700 dark:text-red-400">Delete this exercise</summary>
        <form method="POST" action="{{ route('exercises.destroy', $exercise) }}" class="mt-2">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded bg-red-700 px-3 py-1 text-sm text-white hover:bg-red-600">Delete the exercise and its records</button>
        </form>
    </details>
</x-layouts.app>
