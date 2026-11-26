<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use App\Services\Ai\AnthropicProvider;
use App\Services\Ai\ImageProvider;
use App\Services\Ai\LocalImageProvider;
use App\Services\Ai\OllamaProvider;
use App\Services\Ai\OpenAiCompatibleProvider;
use App\Services\Ai\OpenferenceImageProvider;
use App\Services\Ai\Provider;
use App\Services\AuditLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The AI provider from AI_PROVIDER (spec section 9.1).
     */
    public function register(): void
    {
        $this->app->bind(Provider::class, function () {
            $ai = config('iron-table.ai');

            return match ($ai['provider']) {
                'anthropic' => new AnthropicProvider($ai['base_url'], $ai['api_key'], $ai['model'] ?: 'claude-opus-5-5', $ai['timeout']),
                'openai_compatible' => new OpenAiCompatibleProvider($ai['base_url'], $ai['api_key'], $ai['model'] ?: 'deepseek-flash', $ai['timeout']),
                default => new OllamaProvider($ai['base_url'] ?: 'http://ollama:11434', '', $ai['model'] ?: 'qwen2.5:1.5b', $ai['timeout']),
            };
        });

        $this->app->bind(ImageProvider::class, function () {
            $images = config('iron-table.images');

            return $images['provider'] === 'openference'
                ? new OpenferenceImageProvider($images['base_url'] ?: 'https://api.openference.com/v1', $images['api_key'], $images['model'], $images['timeout'])
                : new LocalImageProvider($images['base_url'] ?: 'http://images:1234', '', 'flux-2-klein-4b', $images['timeout']);
        });
    }

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
        Gate::define('use-images', fn (User $user) => config('iron-table.images.provider') !== '' && Gate::forUser($user)->allows('moderate'));
        Gate::define('use-ai', fn (User $user) => config('iron-table.ai.enabled') && Gate::forUser($user)->allows('moderate'));

        // Spec SEC-06: logins, failed logins, lockouts and password resets go into the audit log.
        // Only the email of a failed login is recorded, never the password.
        Event::listen(Login::class, fn (Login $event) => app(AuditLog::class)->record('login', [], $event->user instanceof User ? $event->user : null));
        Event::listen(Failed::class, fn (Failed $event) => app(AuditLog::class)->record('login.failed', [], null, (string) ($event->credentials['email'] ?? 'unknown')));
        Event::listen(Lockout::class, fn (Lockout $event) => app(AuditLog::class)->record('login.lockout', [], null, (string) $event->request->input('email', 'unknown')));
        Event::listen(PasswordReset::class, fn (PasswordReset $event) => app(AuditLog::class)->record('password.reset', [], $event->user instanceof User ? $event->user : null));
    }
}
