<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

/*
 * IRon Table settings. Values come from .env (see .env.example).
 */

return [

    /*
     * The first administrator account (spec section 11.3, item 5).
     * The app container creates it on the first start, only when the
     * users table is empty. scripts/setup.sh writes these values.
     */
    'initial_admin' => [
        'name' => env('INITIAL_ADMIN_NAME', 'Administrator'),
        'email' => env('INITIAL_ADMIN_EMAIL'),
        'password' => env('INITIAL_ADMIN_PASSWORD'),
    ],

    /*
     * AI assistance (spec section 9). Off by default. The provider is
     * ollama (local), anthropic (Claude) or openai_compatible (for example
     * DeepSeek). An empty model uses the default model of the provider.
     */
    'ai' => [
        'enabled' => (bool) env('AI_ENABLED', false),
        'provider' => env('AI_PROVIDER', 'ollama'),
        'base_url' => (string) env('AI_BASE_URL', ''),
        'api_key' => (string) env('AI_API_KEY', ''),
        'model' => (string) env('AI_MODEL', ''),
        'timeout' => (int) env('AI_TIMEOUT', 180),
    ],

    /*
     * AI pictures for picture artifacts. Off when AI_IMAGE_PROVIDER is empty.
     * local: the "images" container (FLUX.2 klein on the CPU, compose profile
     * "images"). openference: openference.com with the customer's key.
     */
    'images' => [
        'provider' => (string) env('AI_IMAGE_PROVIDER', ''),
        'base_url' => (string) env('AI_IMAGE_BASE_URL', ''),
        'api_key' => (string) env('AI_IMAGE_API_KEY', ''),
        'model' => (string) env('AI_IMAGE_MODEL', ''),
        'timeout' => (int) env('AI_IMAGE_TIMEOUT', 1800),
    ],

];
