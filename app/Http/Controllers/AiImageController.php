<?php

namespace App\Http\Controllers;

use App\Exceptions\CloudflareAiException;
use App\Http\Requests\GenerateAiImageRequest;
use App\Services\CloudflareAiImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiImageController extends Controller
{
    public function __invoke(GenerateAiImageRequest $request, CloudflareAiImageService $service): JsonResponse
    {
        $data = $request->validated();

        try {
            $image = $service->generate($data['prompt'], $data['steps'] ?? 4, $data['seed'] ?? null);

            return response()->json(['success' => true, 'data' => $image]);
        } catch (CloudflareAiException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->safeMessage,
                'code' => $exception->errorCode,
            ], $exception->httpStatus);
        } catch (Throwable $exception) {
            Log::error('Unexpected AI image generation error.', ['exception' => $exception::class]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur inattendue empêche la génération de l’image.',
                'code' => 'internal_error',
            ], 500);
        }
    }
}
