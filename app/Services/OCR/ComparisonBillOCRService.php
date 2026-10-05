<?php

namespace App\Services\OCR;

use App\Services\OCR\Prompts\ComparisonElectricityBillPrompt;
use App\Services\OCR\Responses\ComparisonBillResponseFormatter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Throwable;

class ComparisonBillOCRService
{
    protected string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('gemini.api_key');
        $this->model = config('gemini.model');
    }

    /**
     * Extract bill data for the AC/DC Comparison flow only.
     *
     * This does NOT use the existing WAPDA Bill Management OCR formatter.
     */
    public function extract(UploadedFile $file): array
    {
        try {
            if (!$file->isValid()) {
                return [
                    'success' => false,
                    'message' => 'Invalid uploaded bill file.',
                ];
            }

            if (empty($this->apiKey)) {
                return [
                    'success' => false,
                    'message' => 'Gemini API Key is missing.',
                ];
            }

            $realPath = $file->getRealPath();

            if (!$realPath || !is_readable($realPath)) {
                return [
                    'success' => false,
                    'message' => 'Uploaded bill file could not be read.',
                ];
            }

            $imageData = base64_encode(file_get_contents($realPath));
            $mimeType = $file->getMimeType() ?: 'application/octet-stream';

            $prompt = ComparisonElectricityBillPrompt::generate();

            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent";

            $response = Http::acceptJson()
                ->withHeaders([
                    'x-goog-api-key' => $this->apiKey,
                ])
                ->timeout(config('gemini.timeout', 120))
                ->post($url, [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'text' => $prompt,
                                ],
                                [
                                    'inline_data' => [
                                        'mime_type' => $mimeType,
                                        'data' => $imageData,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => (float) config('gemini.temperature', 0),
                        'topP' => (float) config('gemini.top_p', 0.95),
                        'topK' => (int) config('gemini.top_k', 40),
                        'maxOutputTokens' => (int) config('gemini.max_output_tokens', 2048),
                    ],
                ]);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => 'Gemini Vision API request failed.',
                    'status' => $response->status(),
                    'response' => $response->json(),
                ];
            }

            $result = $response->json();

            $text = data_get(
                $result,
                'candidates.0.content.parts.0.text'
            );

            if (!$text) {
                return [
                    'success' => false,
                    'message' => 'Gemini returned empty response.',
                    'response' => $result,
                ];
            }

            return ComparisonBillResponseFormatter::format($text);
        } catch (Throwable $exception) {
            report($exception);

            return [
                'success' => false,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];
        }
    }
}
