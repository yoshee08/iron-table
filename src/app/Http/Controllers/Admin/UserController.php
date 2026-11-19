<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * User management for administrators (spec section 3). There is no
 * self-registration: an administrator adds each user and gives the user a
 * one-time setup link to choose a password.
 */
class UserController
{
    public function index(): View
    {
        return view('admin.users', [
            'users' => User::query()->orderBy('name')->get(),
            'roles' => Role::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::enum(Role::class)],
        ]);

        // A random password that nobody knows. The user sets a real one with the setup link.
        $user = User::query()->forceCreate([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Str::password(40),
            'role' => Role::from($data['role']),
        ]);

        return $this->withSetupLink($user, 'User added: '.$user->email);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', Rule::enum(Role::class)]]);

        // An administrator cannot change their own role, so at least one administrator always stays.
        if ($user->is($request->user())) {
            return back()->withErrors(['role' => 'You cannot change your own role.']);
        }

        $user->forceFill(['role' => Role::from($data['role'])])->save();

        return redirect()->route('admin.users.index')->with('status', 'Role changed: '.$user->email);
    }

    public function setupLink(User $user): RedirectResponse
    {
        return $this->withSetupLink($user, 'New setup link made: '.$user->email);
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'You cannot delete your own account.']);
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'User deleted: '.$user->email);
    }

    /**
     * Make a password reset token and show its link one time. The administrator
     * gives the link to the user. No email server is needed.
     */
    private function withSetupLink(User $user, string $message): RedirectResponse
    {
        $token = Password::broker()->createToken($user);

        return redirect()->route('admin.users.index')
            ->with('status', $message)
            ->with('setupLink', route('password.reset', ['token' => $token, 'email' => $user->email]));
    }
}
