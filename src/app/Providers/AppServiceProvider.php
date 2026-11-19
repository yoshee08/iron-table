<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Spec SEC-02: passwords have at least 12 characters.
        Password::defaults(fn () => Password::min(12));

        // Spec section 3 and SEC-01: permissions by application role.
        Gate::define('admin', fn (User $user) => $user->role === Role::Admin);
        Gate::define('moderate', fn (User $user) => in_array($user->role, [Role::Admin, Role::Moderator], true));
    }
}
