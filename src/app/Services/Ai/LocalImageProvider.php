<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * The "images" container (compose profile "images"): stable-diffusion.cpp
 * sd-server with FLUX.2 klein 4B on the CPU. It uses the OpenAI-style images
 * endpoint. A picture can take several minutes, so a queued job calls it.
 */
class LocalImageProvider extends ImageProvider
{
    /** FLUX.2 klein is distilled: 4 steps, CFG scale 1 (docs/models/flux2.md of stable-diffusion.cpp). */
    private const SETTINGS = ['sample_params' => ['sample_steps' => 4, 'guidance' => ['txt_cfg' => 1.0]]];

    /** Small pictures keep the CPU time to a few minutes. The container chooses the model at start. */
    private const SIZE = '512x512';

    public function notice(): string
    {
        return 'Local image model (FLUX.2 klein). The prompt stays on this server. A picture takes about 5 to 10 minutes.';
    }

    public function generate(string $prompt): string
    {
        try {
            $response = Http::timeout($this->timeout)->post(rtrim($this->baseUrl, '/').'/v1/images/generations', [
                'prompt' => $prompt.' <sd_cpp_extra_args>'.json_encode(self::SETTINGS).'</sd_cpp_extra_args>',
                'n' => 1,
                'size' => self::SIZE,
                'output_format' => 'png',
            ]);
        } catch (ConnectionException) {
            throw new AiException('IRon Table cannot reach the local image service, or the picture took too long. Start it with: docker compose --profile images up -d');
        }

        if ($response->failed()) {
            $error = $response->json('error.message') ?? $response->json('error');
            throw new AiException('The local image service returned an error: '.(is_string($error) ? $error : $response->status()));
        }
        $image = base64_decode((string) $response->json('data.0.b64_json'), true);
        if ($image === false || $image === '') {
            throw new AiException('The local image service gave no picture. Try again.');
        }

        return $image;
    }
}
