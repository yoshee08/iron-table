<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature;

use App\Services\Ai\AiException;
use App\Services\Ai\AnthropicProvider;
use App\Services\Ai\OllamaProvider;
use App\Services\Ai\OpenAiCompatibleProvider;
use App\Services\Ai\Provider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiProviderTest extends TestCase
{
    private const SCHEMA = [
        'type' => 'object',
        'properties' => ['title' => ['type' => 'string'], 'points' => ['type' => 'integer']],
        'required' => ['title', 'points'],
    ];

    public function test_the_setting_selects_the_provider(): void
    {
        foreach (['ollama' => OllamaProvider::class, 'anthropic' => AnthropicProvider::class, 'openai_compatible' => OpenAiCompatibleProvider::class] as $name => $class) {
            config(['iron-table.ai.provider' => $name]);
            $this->assertInstanceOf($class, app(Provider::class));
        }
    }

    public function test_ollama_gets_the_schema_and_the_answer_is_checked(): void
    {
        Http::fake(['ollama:11434/api/chat' => Http::sequence()
            ->push(['message' => ['content' => '{"title": "Phishing", "points": 2}']])
            ->push(['message' => ['content' => '{"title": "Phishing"}']])
            ->push(['error' => 'model "qwen2.5:1.5b" not found'], 404),
        ]);
        $ai = new OllamaProvider('http://ollama:11434', '', 'qwen2.5:1.5b', 30);

        $this->assertSame(['title' => 'Phishing', 'points' => 2], $ai->json('Rules.', 'Task.', self::SCHEMA));
        Http::assertSent(fn (Request $request) => $request['format'] === self::SCHEMA && $request['model'] === 'qwen2.5:1.5b' && $request['stream'] === false);

        $this->assertAiError(fn () => $ai->json('Rules.', 'Task.', self::SCHEMA), 'The AI gave an answer in the wrong format.');
        $this->assertAiError(fn () => $ai->json('Rules.', 'Task.', self::SCHEMA), 'docker compose exec ollama ollama pull qwen2.5:1.5b');
        $this->assertStringContainsString('stays on this server', $ai->notice());
    }

    public function test_an_openai_compatible_service_gets_json_mode_and_the_key(): void
    {
        Http::fake(['api.deepseek.com/chat/completions' => Http::sequence()
            ->push(['choices' => [['message' => ['content' => '{"title": "Lost laptop", "points": 1}']]]])
            ->push(['error' => ['message' => 'Authentication Fails']], 401),
        ]);
        $ai = new OpenAiCompatibleProvider('https://api.deepseek.com', 'secret-key', 'deepseek-flash', 30);

        $this->assertSame(['title' => 'Lost laptop', 'points' => 1], $ai->json('Rules.', 'Task.', self::SCHEMA));
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer secret-key')
            && $request['response_format'] === ['type' => 'json_object']
            && str_contains($request['messages'][0]['content'], '"required":["title","points"]'));

        $this->assertAiError(fn () => $ai->json('Rules.', 'Task.', self::SCHEMA), 'The AI service refused the API key.');
        $this->assertStringContainsString('DeepSeek processes data in China', $ai->notice());
        $this->assertStringNotContainsString('China', (new OpenAiCompatibleProvider('https://api.openai.com/v1', 'k', 'm', 30))->notice());
    }

    private function assertAiError(callable $call, string $message): void
    {
        try {
            $call();
            $this->fail('No AiException');
        } catch (AiException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }
}
