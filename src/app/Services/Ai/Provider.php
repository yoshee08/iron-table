<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Services\Ai;

/**
 * One AI provider (spec section 9.1). The application asks for one small JSON
 * answer at a time and refuses an answer that does not match the schema.
 */
abstract class Provider
{
    public function __construct(
        protected string $baseUrl,
        protected string $apiKey,
        protected string $model,
        protected int $timeout,
    ) {}

    /**
     * What the moderator sees about the provider: where the scenario text goes.
     */
    abstract public function notice(): string;

    /**
     * Send one request. Return the text of the answer (JSON).
     *
     * @param  array<string, mixed>  $schema
     *
     * @throws AiException
     */
    abstract protected function send(string $instructions, string $request, array $schema): string;

    /**
     * @param  array<string, mixed>  $schema  a JSON schema with type, properties, required, items and enum only
     * @return array<string, mixed>
     *
     * @throws AiException
     */
    public function json(string $instructions, string $request, array $schema): array
    {
        $answer = json_decode($this->send($instructions, $request, $schema), true);

        if (! is_array($answer) || ! self::matches($answer, $schema)) {
            throw new AiException('The AI gave an answer in the wrong format. Try again, or write this part yourself.');
        }

        return $answer;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private static function matches(mixed $value, array $schema): bool
    {
        if (isset($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
            return false;
        }

        return match ($schema['type']) {
            'string' => is_string($value),
            'integer' => is_int($value),
            'array' => is_array($value) && array_is_list($value)
                && array_filter($value, fn ($item) => ! self::matches($item, $schema['items'])) === [],
            'object' => is_array($value)
                && array_filter($schema['required'], fn ($key) => ! array_key_exists($key, $value) || ! self::matches($value[$key], $schema['properties'][$key])) === [],
            default => false,
        };
    }
}
