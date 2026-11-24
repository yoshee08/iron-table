<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * openference.com: an API gateway with image models (for example Gemini image
 * models) behind its chat endpoint. Its documentation does not describe the
 * image answer, so this reads the common gateway shapes: message.images[] or
 * image parts in message.content, each with a data URL.
 */
class OpenferenceImageProvider extends ImageProvider
{
    public function notice(): string
    {
        return "openference.com (model {$this->model}). The picture prompt goes to openference.com. Its website does not say who runs the service or how long it keeps data.";
    }

    public function generate(string $prompt): string
    {
        try {
            $response = Http::withToken($this->apiKey)->timeout($this->timeout)->post(rtrim($this->baseUrl, '/').'/chat/completions', [
                'model' => $this->model,
                'modalities' => ['image', 'text'],
                'messages' => [['role' => 'user', 'content' => 'Make one photo-realistic picture: '.$prompt]],
            ]);
        } catch (ConnectionException) {
            throw new AiException('IRon Table cannot reach openference.com. Check the network connection of the server.');
        }

        if ($response->unauthorized()) {
            throw new AiException('openference.com refused the API key. Check AI_IMAGE_API_KEY.');
        }
        if ($response->failed()) {
            throw new AiException('openference.com returned an error: '.($response->json('error.message') ?? $response->status()));
        }

        $message = (array) $response->json('choices.0.message');
        $urls = array_column(array_column($message['images'] ?? [], 'image_url'), 'url');
        if (is_array($message['content'] ?? null)) {
            $urls = array_merge($urls, array_column(array_column($message['content'], 'image_url'), 'url'));
        } elseif (is_string($message['content'] ?? null) && preg_match('#data:image/[a-z]+;base64,[A-Za-z0-9+/=]+#', $message['content'], $m)) {
            $urls[] = $m[0];
        }

        foreach ($urls as $url) {
            if (is_string($url) && preg_match('#^data:image/(png|jpeg|webp);base64,(.+)$#s', $url, $m)) {
                $image = base64_decode($m[2], true);
                if ($image !== false && $image !== '') {
                    return $image;
                }
            }
        }

        throw new AiException('openference.com gave no picture. Check that AI_IMAGE_MODEL is an image model, or try again.');
    }
}
