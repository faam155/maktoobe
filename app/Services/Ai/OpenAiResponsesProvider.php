<?php

namespace App\Services\Ai;

use App\Contracts\AiProvider;
use App\Data\AiGenerationResult;
use App\Exceptions\AiProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class OpenAiResponsesProvider implements AiProvider
{
    public function generate(array $messages, string $model, array $settings, string $safetyIdentifier): AiGenerationResult
    {
        $key = (string) config('ai.openai.key');
        if ($key === '') {
            throw new AiProviderException('not_configured');
        }
        $payload = ['model' => $model, 'input' => $messages, 'store' => false,
            'max_output_tokens' => $settings['max_output_tokens'], 'safety_identifier' => $safetyIdentifier];
        if ($settings['temperature'] !== null) {
            $payload['temperature'] = $settings['temperature'];
        }

        try {
            $request = Http::acceptJson()->asJson()->withToken($key)
                ->connectTimeout(config('ai.connect_timeout'))->timeout(config('ai.timeout'));
            if (filled(config('ai.openai.organization'))) {
                $request = $request->withHeaders(['OpenAI-Organization' => config('ai.openai.organization')]);
            }
            if (filled(config('ai.openai.project'))) {
                $request = $request->withHeaders(['OpenAI-Project' => config('ai.openai.project')]);
            }
            $response = $request->post(config('ai.openai.base_url').'/responses', $payload);
        } catch (ConnectionException) {
            throw new AiProviderException('timeout');
        }

        if (! $response->successful()) {
            $code = match ($response->status()) {
                408 => 'timeout', 429 => 'rate_limited', 401, 403 => 'provider_auth', default => $response->serverError() ? 'provider_unavailable' : 'provider_rejected'
            };
            throw new AiProviderException($code);
        }
        $json = $response->json();
        $content = collect($json['output'] ?? [])->flatMap(fn ($item) => $item['content'] ?? [])
            ->where('type', 'output_text')->pluck('text')->implode("\n");
        if (trim($content) === '') {
            throw new AiProviderException('empty_response');
        }

        return new AiGenerationResult($content, $json['id'] ?? null, data_get($json, 'usage.input_tokens'), data_get($json, 'usage.output_tokens'), data_get($json, 'usage.total_tokens'));
    }
}
