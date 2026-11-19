<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * A service with an OpenAI-style chat API, for example DeepSeek or OpenAI.
 * JSON mode is the common part of these APIs, so the schema goes in the
 * instructions and Provider::json() checks the answer.
 */
class OpenAiCompatibleProvider extends Provider
{
    public function notice(): string
    {
        $host = (string) parse_url($this->baseUrl, PHP_URL_HOST);
        $notice = "AI service {$host} (model {$this->model}). The scenario text goes to this service. The IR plan and the organization profile values do not.";

        if (str_contains($host, 'deepseek')) {
            $notice .= ' Warning: DeepSeek processes data in China. Do not use it if your organization handles Controlled Unclassified Information (CUI) or export-controlled data.';
        }

        return $notice;
    }

    protected function send(string $instructions, string $request, array $schema): string
    {
        try {
            $response = Http::withToken($this->apiKey)->timeout($this->timeout)->post(rtrim($this->baseUrl, '/').'/chat/completions', [
                'model' => $this->model,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $instructions."\nAnswer with one JSON object that matches this JSON schema:\n".json_encode($schema)],
                    ['role' => 'user', 'content' => $request],
                ],
            ]);
        } catch (ConnectionException) {
            throw new AiException('IRon Table cannot reach the AI service. Check AI_BASE_URL.');
        }

        if ($response->unauthorized()) {
            throw new AiException('The AI service refused the API key. Check AI_API_KEY.');
        }
        if ($response->failed()) {
            throw new AiException('The AI service returned an error: '.($response->json('error.message') ?? $response->status()));
        }

        return (string) $response->json('choices.0.message.content');
    }
}
