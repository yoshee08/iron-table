<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature;

use App\Services\Ai\AiException;
use App\Services\Ai\ImageProvider;
use App\Services\Ai\LocalImageProvider;
use App\Services\Ai\OpenferenceImageProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\FakeImages;
use Tests\TestCase;

class ImageProviderTest extends TestCase
{
    public function test_the_setting_selects_the_image_provider(): void
    {
        config(['iron-table.images.provider' => 'local']);
        $this->assertInstanceOf(LocalImageProvider::class, app(ImageProvider::class));
        config(['iron-table.images.provider' => 'openference']);
        $this->assertInstanceOf(OpenferenceImageProvider::class, app(ImageProvider::class));
    }

    public function test_the_local_server_gets_the_flux_settings_and_returns_a_png(): void
    {
        Http::fake(['images:1234/v1/images/generations' => Http::response(['created' => 1, 'data' => [['b64_json' => FakeImages::PNG]]])]);
        $images = new LocalImageProvider('http://images:1234', '', 'flux-2-klein-4b', 30);

        $this->assertSame(FakeImages::png(), $images->generate('An open door at night'));
        Http::assertSent(function (Request $request) {
            preg_match('#^An open door at night <sd_cpp_extra_args>(.+)</sd_cpp_extra_args>$#', $request['prompt'], $m);

            return $request['size'] === '512x512' && $request['output_format'] === 'png'
                && json_decode($m[1] ?? 'null', true) == ['sample_params' => ['sample_steps' => 4, 'guidance' => ['txt_cfg' => 1]]];
        });
    }

    public function test_the_local_server_errors_are_clear(): void
    {
        $images = new LocalImageProvider('http://images:1234', '', 'flux-2-klein-4b', 30);

        Http::fake(['*' => Http::failedConnection()]);
        $this->assertImageError(fn () => $images->generate('x'), 'docker compose --profile images up -d');
    }

    public function test_openference_returns_the_picture_from_the_chat_answer(): void
    {
        $dataUrl = 'data:image/png;base64,'.FakeImages::PNG;
        Http::fake(['api.openference.com/v1/chat/completions' => Http::sequence()
            ->push(['choices' => [['message' => ['content' => 'Here it is.', 'images' => [['type' => 'image_url', 'image_url' => ['url' => $dataUrl]]]]]]])
            ->push(['choices' => [['message' => ['content' => [['type' => 'text', 'text' => 'Here.'], ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]]]]]]])
            ->push(['choices' => [['message' => ['content' => 'I cannot make pictures.']]]])
            ->push(['error' => ['message' => 'Invalid key']], 401),
        ]);
        $images = new OpenferenceImageProvider('https://api.openference.com/v1', 'customer-key', 'gemini-image', 30);

        $this->assertSame(FakeImages::png(), $images->generate('A USB stick in a car park'));
        $this->assertSame(FakeImages::png(), $images->generate('A USB stick in a car park'));
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer customer-key') && $request['model'] === 'gemini-image');
        $this->assertImageError(fn () => $images->generate('x'), 'openference.com gave no picture.');
        $this->assertImageError(fn () => $images->generate('x'), 'openference.com refused the API key.');
        $this->assertStringContainsString('does not say who runs the service', $images->notice());
    }

    private function assertImageError(callable $call, string $message): void
    {
        try {
            $call();
            $this->fail('No AiException');
        } catch (AiException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }
}
