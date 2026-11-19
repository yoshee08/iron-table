<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests;

use App\Services\Ai\AiException;
use App\Services\Ai\Provider;

/**
 * An AI provider for tests: it returns the prepared answers in order and keeps the requests.
 */
class FakeAi extends Provider
{
    /** @var list<string> */
    public array $requests = [];

    /**
     * @param  list<array<string, mixed>|string>  $answers  an array is sent as JSON; a string is an error message
     */
    public function __construct(public array $answers = [])
    {
        parent::__construct('', '', 'fake', 1);
    }

    public function notice(): string
    {
        return 'Test AI. The scenario text stays in the test.';
    }

    protected function send(string $instructions, string $request, array $schema): string
    {
        $this->requests[] = $request;
        $answer = array_shift($this->answers);
        if (is_string($answer)) {
            throw new AiException($answer);
        }

        return (string) json_encode($answer);
    }
}
