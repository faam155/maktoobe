<?php

$models = array_values(array_filter(array_map('trim', explode(',', (string) env('OPENAI_MODELS', 'gpt-5-mini')))));

return [
    'provider' => env('AI_PROVIDER', 'openai'),
    'default_model' => env('OPENAI_DEFAULT_MODEL', $models[0] ?? 'gpt-5-mini'),
    'models' => $models,
    'role_models' => json_decode((string) env('AI_ROLE_MODELS', '{}'), true) ?: [],
    'max_output_tokens' => (int) env('OPENAI_MAX_OUTPUT_TOKENS', 1200),
    'temperature' => env('OPENAI_TEMPERATURE') === null ? null : (float) env('OPENAI_TEMPERATURE'),
    'timeout' => (int) env('OPENAI_TIMEOUT', 60),
    'connect_timeout' => (int) env('OPENAI_CONNECT_TIMEOUT', 10),
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'base_url' => rtrim((string) env('OPENAI_BASE_URL', 'https://api.openai.com/v1'), '/'),
        'organization' => env('OPENAI_ORGANIZATION'),
        'project' => env('OPENAI_PROJECT'),
    ],
];
