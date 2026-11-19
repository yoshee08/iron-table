<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use App\Services\Ai\AnthropicProvider;
use App\Services\Ai\OllamaProvider;
use App\Services\Ai\OpenAiCompatibleProvider;
use App\Services\Ai\Provider;
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
                'openai_compatible' => new OpenAiCompatibleProvider($ai['base_url'], $ai['api_key'], $ai['model'] ?: 'deepseek-chat', $ai['timeout']),
                default => new OllamaProvider($ai['base_url'] ?: 'http://ollama:11434', '', $ai['model'] ?: 'qwen2.5:1.5b', $ai['timeout']),
            };
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
        Gate::define('use-ai', fn (User $user) => config('iron-table.ai.enabled') && Gate::forUser($user)->allows('moderate'));
    }
}
