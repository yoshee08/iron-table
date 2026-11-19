<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * The local model in the "ollama" container (compose profile "ai"). The
 * schema goes in "format", so the model can only write JSON of that shape.
 */
class OllamaProvider extends Provider
{
    public function notice(): string
    {
        return "Local AI (model {$this->model}). The scenario text stays on this server.";
    }

    protected function send(string $instructions, string $request, array $schema): string
    {
        try {
            $response = Http::baseUrl($this->baseUrl)->timeout($this->timeout)->post('/api/chat', [
                'model' => $this->model,
                'stream' => false,
                'format' => $schema,
                'messages' => [
                    ['role' => 'system', 'content' => $instructions],
                    ['role' => 'user', 'content' => $request],
                ],
            ]);
        } catch (ConnectionException) {
            throw new AiException('IRon Table cannot reach the local AI. Start it with: docker compose --profile ai up -d');
        }

        if ($response->notFound()) {
            throw new AiException("The local AI does not have the model {$this->model}. Download it with: docker compose exec ollama ollama pull {$this->model}");
        }
        if ($response->failed()) {
            throw new AiException('The local AI returned an error: '.($response->json('error') ?? $response->status()));
        }

        return (string) $response->json('message.content');
    }
}
