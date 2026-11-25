<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests;

use App\Services\Ai\AiException;
use App\Services\Ai\ImageProvider;

/**
 * An image provider for tests: it returns a real 1x1 PNG, or fails with the given message.
 */
class FakeImages extends ImageProvider
{
    public const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    /** @var list<string> */
    public array $prompts = [];

    public function __construct(private ?string $error = null)
    {
        parent::__construct('', '', 'fake', 1);
    }

    public static function png(): string
    {
        return (string) base64_decode(self::PNG, true);
    }

    public function notice(): string
    {
        return 'Test pictures.';
    }

    public function generate(string $prompt): string
    {
        $this->prompts[] = $prompt;
        if ($this->error !== null) {
            throw new AiException($this->error);
        }

        return self::png();
    }
}
