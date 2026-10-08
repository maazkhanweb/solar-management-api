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
        /*
        |--------------------------------------------------------------------------
        | Keep compatibility with existing Gemini configuration
        |--------------------------------------------------------------------------
        */

        $this->apiKey =
            (string) (
                config('gemini.api_key')
                ?: env('GEMINI_API_KEY')
            );

        $this->model =
            (string) (
                config('gemini.model')
                ?: env(
                    'GEMINI_MODEL',
                    'gemini-2.5-flash'
                )
            );
    }

    /**
     * Main OCR entry point.
     */
    public function extract(
        UploadedFile $file
    ): array {

        try {

            /*
            |--------------------------------------------------------------------------
            | File validation
            |--------------------------------------------------------------------------
            */

            if (
                !$file->isValid()
            ) {

                return [
                    'success' => false,

                    'message' =>
                        'Invalid uploaded bill file.',
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | API key
            |--------------------------------------------------------------------------
            */

            if (
                trim($this->apiKey) === ''
            ) {

                return [
                    'success' => false,

                    'message' =>
                        'Gemini API key is missing.',
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | File path
            |--------------------------------------------------------------------------
            */

            $path =
                $file->getRealPath();

            if (
                !$path
                ||
                !is_readable($path)
            ) {

                return [
                    'success' => false,

                    'message' =>
                        'Uploaded bill file cannot be read.',
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | File contents
            |--------------------------------------------------------------------------
            */

            $contents =
                file_get_contents($path);

            if (
                $contents === false
            ) {

                return [
                    'success' => false,

                    'message' =>
                        'Unable to read uploaded bill.',
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | MIME type
            |--------------------------------------------------------------------------
            */

            $mimeType =
                $file->getMimeType()
                ?: 'image/jpeg';

            /*
            |--------------------------------------------------------------------------
            | Base64
            |--------------------------------------------------------------------------
            */

            $base64 =
                base64_encode(
                    $contents
                );

            /*
            |--------------------------------------------------------------------------
            | Prompt
            |--------------------------------------------------------------------------
            */

            $prompt =
                ComparisonElectricityBillPrompt::generate();

            /*
            |--------------------------------------------------------------------------
            | First OCR request
            |--------------------------------------------------------------------------
            */

            $firstResponse =
                $this->sendGeminiRequest(
                    $prompt,
                    $mimeType,
                    $base64
                );

            if (
                !$firstResponse['success']
            ) {

                return $firstResponse;
            }

            /*
            |--------------------------------------------------------------------------
            | Extract all text parts
            |--------------------------------------------------------------------------
            */

            $text =
                $this->extractAllText(
                    $firstResponse['response']
                );

            if (
                trim($text) === ''
            ) {

                return [
                    'success' => false,

                    'message' =>
                        'Gemini returned an empty OCR response.',

                    'response' =>
                        $firstResponse['response'],
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | Format first response
            |--------------------------------------------------------------------------
            */

            $formatted =
                ComparisonBillResponseFormatter::format(
                    $text
                );

            if (
                $formatted['success'] === true
            ) {

                return $formatted;
            }

            /*
            |--------------------------------------------------------------------------
            | SECOND PASS
            |--------------------------------------------------------------------------
            |
            | Gemini sometimes returns incomplete JSON when the bill is large.
            |
            | We send the OCR response to Gemini again and ask it to repair
            | the structure WITHOUT losing any extracted information.
            |
            */

            $repairPrompt =
                $this->buildRepairPrompt(
                    $text
                );

            $repairResponse =
                $this->sendGeminiRequest(
                    $repairPrompt,
                    'text/plain',
                    null
                );

            if (
                $repairResponse['success']
            ) {

                $repairedText =
                    $this->extractAllText(
                        $repairResponse['response']
                    );

                if (
                    trim($repairedText) !== ''
                ) {

                    $repaired =
                        ComparisonBillResponseFormatter::format(
                            $repairedText
                        );

                    if (
                        $repaired['success'] === true
                    ) {

                        return $repaired;
                    }
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Final failure
            |--------------------------------------------------------------------------
            */

            return [

                'success' => false,

                'message' =>
                    'Gemini returned invalid JSON after OCR and repair attempts.',

                'raw_response' =>
                    $text,

                'first_response' =>
                    $firstResponse['response'],

                'repair_response' =>
                    $repairResponse['response']
                    ?? null,
            ];

        } catch (Throwable $exception) {

            report($exception);

            return [

                'success' => false,

                'message' =>
                    $exception->getMessage(),
            ];
        }
    }

    /**
     * Send request to Gemini.
     */
    protected function sendGeminiRequest(
        string $prompt,
        string $mimeType,
        ?string $base64
    ): array {

        /*
        |--------------------------------------------------------------------------
        | Endpoint
        |--------------------------------------------------------------------------
        */

        $url =
            'https://generativelanguage.googleapis.com'
            . '/v1beta/models/'
            . $this->model
            . ':generateContent';

        /*
        |--------------------------------------------------------------------------
        | Parts
        |--------------------------------------------------------------------------
        */

        $parts = [
            [
                'text' => $prompt,
            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | Add document/image when provided
        |--------------------------------------------------------------------------
        */

        if (
            $base64 !== null
        ) {

            $parts[] = [

                'inline_data' => [

                    'mime_type' =>
                        $mimeType,

                    'data' =>
                        $base64,
                ],
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Request
        |--------------------------------------------------------------------------
        */

        $payload = [

            'contents' => [

                [
                    'role' => 'user',

                    'parts' =>
                        $parts,
                ],
            ],

            'generationConfig' => [

                /*
                | Important:
                | Gemini is explicitly instructed to produce JSON.
                */

                'responseMimeType' =>
                    'application/json',

                'temperature' =>
                    0,

                'topP' =>
                    0.9,

                /*
                | Large enough for complete bill data.
                */

                'maxOutputTokens' =>
                    16384,
            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | API request
        |--------------------------------------------------------------------------
        */

        $response =
            Http::acceptJson()
                ->withHeaders([
                    'x-goog-api-key' =>
                        $this->apiKey,

                    'Content-Type' =>
                        'application/json',
                ])
                ->timeout(240)
                ->post(
                    $url,
                    $payload
                );

        /*
        |--------------------------------------------------------------------------
        | HTTP error
        |--------------------------------------------------------------------------
        */

        if (
            !$response->successful()
        ) {

            return [

                'success' => false,

                'message' =>
                    'Gemini Vision API request failed.',

                'status' =>
                    $response->status(),

                'response' =>
                    $response->json(),

                'body' =>
                    $response->body(),
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | JSON response
        |--------------------------------------------------------------------------
        */

        $json =
            $response->json();

        /*
        |--------------------------------------------------------------------------
        | Gemini API-level error
        |--------------------------------------------------------------------------
        */

        if (
            isset($json['error'])
        ) {

            return [

                'success' => false,

                'message' =>
                    $json['error']['message']
                    ?? 'Gemini API error.',

                'status' =>
                    $response->status(),

                'response' =>
                    $json,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Check candidate
        |--------------------------------------------------------------------------
        */

        $candidate =
            data_get(
                $json,
                'candidates.0'
            );

        if (
            !$candidate
        ) {

            return [

                'success' => false,

                'message' =>
                    'Gemini did not return an OCR candidate.',

                'response' =>
                    $json,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Finish reason
        |--------------------------------------------------------------------------
        */

        $finishReason =
            data_get(
                $candidate,
                'finishReason'
            );

        /*
        |--------------------------------------------------------------------------
        | Return response even when MAX_TOKENS occurred.
        |
        | The caller can inspect it.
        |--------------------------------------------------------------------------
        */

        return [

            'success' => true,

            'response' =>
                $json,

            'finish_reason' =>
                $finishReason,
        ];
    }

    /**
     * Extract text from ALL Gemini parts.
     *
     * Previous implementation commonly used:
     *
     * candidates.0.content.parts.0.text
     *
     * which can lose data if Gemini returns multiple parts.
     */
    protected function extractAllText(
        array $response
    ): string {

        $parts =
            data_get(
                $response,
                'candidates.0.content.parts',
                []
            );

        if (
            !is_array($parts)
        ) {

            return '';
        }

        $text = '';

        foreach (
            $parts as $part
        ) {

            if (
                isset(
                    $part['text']
                )
                &&
                is_string(
                    $part['text']
                )
            ) {

                $text .=
                    $part['text']
                    . "\n";
            }
        }

        return trim($text);
    }

    /**
     * Build a JSON-repair prompt.
     *
     * This is only used if the first OCR response is malformed.
     */
    protected function buildRepairPrompt(
        string $ocrText
    ): string {

        /*
        |--------------------------------------------------------------------------
        | Keep prompt reasonably small
        |--------------------------------------------------------------------------
        */

        return <<<PROMPT
You are a JSON repair specialist.

The following text was produced by an electricity-bill OCR system.

It may contain:
- malformed JSON
- markdown fences
- trailing commas
- incomplete formatting
- escaped characters
- multiple JSON fragments

Your task is ONLY to convert the supplied OCR output into ONE valid JSON object.

IMPORTANT:

1. Do not invent information.
2. Do not remove extracted information.
3. Preserve every field you can identify.
4. Preserve meter readings.
5. Preserve reading history.
6. Preserve consumption history.
7. Preserve charge breakdown.
8. Preserve tax breakdown.
9. Preserve payment information.
10. Preserve additional information.
11. Unknown fields must remain in the JSON.
12. Return ONLY valid JSON.
13. No markdown.
14. No ```json.
15. No explanation.

OCR OUTPUT:

{$ocrText}
PROMPT;
    }
}