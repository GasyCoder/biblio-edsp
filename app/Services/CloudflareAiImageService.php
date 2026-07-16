<?php

namespace App\Services;

use App\Exceptions\CloudflareAiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudflareAiImageService
{
    /** @return array{image: string, seed: int, prompt: string} */
    public function generate(string $prompt, int $steps = 4, ?int $seed = null): array
    {
        $accountId = config('services.cloudflare.account_id');
        $token = config('services.cloudflare.api_token');
        $model = config('services.cloudflare.ai_image_model', '@cf/black-forest-labs/flux-1-schnell');

        if (blank($accountId) || blank($token)) {
            throw new CloudflareAiException('La génération IA n’est pas configurée.', 503, 'configuration_missing');
        }

        $seed ??= random_int(1, 2147483647);

        try {
            $response = Http::asJson()
                ->acceptJson()
                ->withToken($token)
                ->connectTimeout(10)
                ->timeout(90)
                ->post("https://api.cloudflare.com/client/v4/accounts/{$accountId}/ai/run/{$model}", [
                    'prompt' => $prompt,
                    'steps' => $steps,
                    'seed' => $seed,
                ]);
        } catch (ConnectionException) {
            Log::warning('Cloudflare Workers AI request timed out or failed to connect.');
            throw new CloudflareAiException('Le service de génération ne répond pas. Réessayez dans quelques instants.', 504, 'timeout');
        }

        if ($response->status() === 401 || $response->status() === 403) {
            Log::warning('Cloudflare Workers AI authentication rejected.', ['status' => $response->status()]);
            throw new CloudflareAiException('Le service de génération est temporairement indisponible.', 503, 'authentication_failed');
        }

        if ($response->status() === 429) {
            Log::notice('Cloudflare Workers AI quota or rate limit reached.');
            throw new CloudflareAiException('Le quota de génération est atteint. Réessayez plus tard.', 429, 'quota_exceeded');
        }

        if ($response->serverError() || $response->failed()) {
            Log::warning('Cloudflare Workers AI returned an error.', ['status' => $response->status()]);
            throw new CloudflareAiException('Cloudflare Workers AI est momentanément indisponible.', 502, 'provider_unavailable');
        }

        $image = $response->json('result.image');
        if (! is_string($image) || $image === '' || base64_decode($image, true) === false) {
            Log::warning('Cloudflare Workers AI response did not contain a valid image.');
            throw new CloudflareAiException('La réponse reçue ne contient aucune image valide.', 502, 'image_missing');
        }

        return ['image' => 'data:image/jpeg;base64,'.$image, 'seed' => $seed, 'prompt' => $prompt];
    }
}
