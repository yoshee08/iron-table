<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Services\Ai;

/**
 * One image provider (spec section 14, image artifacts): the local
 * stable-diffusion.cpp server, or openference.com.
 */
abstract class ImageProvider
{
    public function __construct(
        protected string $baseUrl,
        protected string $apiKey,
        protected string $model,
        protected int $timeout,
    ) {}

    /**
     * What the moderator sees about the provider: where the picture prompt goes.
     */
    abstract public function notice(): string;

    /**
     * Make one picture. Returns the image file contents (PNG, JPEG or WebP).
     *
     * @throws AiException
     */
    abstract public function generate(string $prompt): string;
}
