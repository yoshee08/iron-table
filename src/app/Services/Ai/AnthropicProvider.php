<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Services\Ai;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;

/**
 * Claude, through the official Anthropic PHP SDK. Structured outputs make the
 * answer match the schema. Server-side fallback retries on another model if
 * the requested model declines.
 */
class AnthropicProvider extends Provider
{
    public function notice(): string
    {
        return "Claude (Anthropic, model {$this->model}). The scenario text goes to Anthropic. The IR plan and the organization profile values do not.";
    }

    protected function send(string $instructions, string $request, array $schema): string
    {
        $client = new Client(apiKey: $this->apiKey ?: null, baseUrl: $this->baseUrl ?: null);

        try {
            $message = $client->beta->messages->create(
                model: $this->model,
                maxTokens: 16000,
                system: $instructions,
                messages: [['role' => 'user', 'content' => $request]],
                outputConfig: [
                    'effort' => 'medium',
                    'format' => ['type' => 'json_schema', 'schema' => $schema],
                ],
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (AuthenticationException) {
            throw new AiException('Anthropic refused the API key. Check AI_API_KEY.');
        } catch (RateLimitException) {
            throw new AiException('Anthropic is busy or the rate limit is reached. Try again in a minute.');
        } catch (APIStatusException $e) {
            throw new AiException('Anthropic returned an error: '.$e->getMessage());
        } catch (APIConnectionException) {
            throw new AiException('IRon Table cannot reach Anthropic. Check the network connection of the server.');
        }

        if ($message->stopReason === 'refusal') {
            throw new AiException('Claude declined this request. Change the scenario text, or write this part yourself.');
        }
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                return $block->text;
            }
        }

        throw new AiException('Claude gave no answer. Try again.');
    }
}
