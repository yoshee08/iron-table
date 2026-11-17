{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<x-layouts.app title="Users">
    @if (session('status'))
        <p role="status" class="mb-4 rounded border border-green-300 bg-green-50 p-3 text-sm dark:border-green-700 dark:bg-green-950">{{ session('status') }}</p>
    @endif

    @if (session('setupLink'))
        <div class="mb-6 rounded border border-amber-300 bg-amber-50 p-3 text-sm dark:border-amber-700 dark:bg-amber-950">
            <p class="mb-2 font-medium">Setup link (shown one time)</p>
            <p class="mb-2">Give this link to the user. It lets the user choose a password. It works for {{ config('auth.passwords.users.expire') }} minutes.</p>
            <input type="text" readonly value="{{ session('setupLink') }}" aria-label="Setup link" class="w-full rounded border border-zinc-300 bg-white px-2 py-1 font-mono text-xs dark:border-zinc-700 dark:bg-zinc-950">
        </div>
    @endif

    @if ($errors->any())
        <div role="alert" class="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm dark:border-red-700 dark:bg-red-950">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <section class="mb-10 max-w-md" aria-labelledby="add-heading">
        <h2 id="add-heading" class="mb-4 text-lg font-medium">Add a user</h2>
        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            <x-field name="name" label="Name" autocomplete="off" required />
            <x-field name="email" label="Email" type="email" autocomplete="off" required />
            <div class="mb-4">
                <label for="role" class="mb-1 block text-sm font-medium">Role</label>
                <select id="role" name="role" class="w-full rounded border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-950">
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}" @selected(old('role', 'user') === $role->value)>{{ $role->label() }}</option>
                    @endforeach
                </select>
            </div>
            <x-button>Add the user</x-button>
        </form>
    </section>

    <section aria-labelledby="list-heading">
        <h2 id="list-heading" class="mb-4 text-lg font-medium">All users ({{ $users->count() }})</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-300 dark:border-zinc-700">
                    <tr>
                        <th scope="col" class="py-2 pr-4">Name</th>
                        <th scope="col" class="py-2 pr-4">Email</th>
                        <th scope="col" class="py-2 pr-4">Role</th>
                        <th scope="col" class="py-2 pr-4">Two-factor</th>
                        <th scope="col" class="py-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr class="border-b border-zinc-200 align-top dark:border-zinc-800">
                            <td class="py-2 pr-4">{{ $user->name }}</td>
                            <td class="py-2 pr-4">{{ $user->email }}</td>
                            <td class="py-2 pr-4">
                                @if ($user->is(auth()->user()))
                                    {{ $user->role->label() }} (you)
                                @else
                                    <form method="POST" action="{{ route('admin.users.role', $user) }}" class="flex gap-2">
                                        @csrf
                                        @method('PUT')
                                        <select name="role" aria-label="Role of {{ $user->name }}" class="rounded border border-zinc-300 bg-white px-2 py-1 dark:border-zinc-700 dark:bg-zinc-950">
                                            @foreach ($roles as $role)
                                                <option value="{{ $role->value }}" @selected($user->role === $role)>{{ $role->label() }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="underline underline-offset-4">Save</button>
                                    </form>
                                @endif
                            </td>
                            <td class="py-2 pr-4">{{ $user->hasEnabledTwoFactorAuthentication() ? 'On' : 'Off' }}</td>
                            <td class="py-2">
                                <form method="POST" action="{{ route('admin.users.setup-link', $user) }}" class="mb-1">
                                    @csrf
                                    <button type="submit" class="underline underline-offset-4">New setup link</button>
                                </form>
                                @unless ($user->is(auth()->user()))
                                    <details>
                                        <summary class="cursor-pointer text-red-700 dark:text-red-400">Delete</summary>
                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="mt-1">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded bg-red-700 px-2 py-1 text-white hover:bg-red-600">Delete {{ $user->name }}</button>
                                        </form>
                                    </details>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>
