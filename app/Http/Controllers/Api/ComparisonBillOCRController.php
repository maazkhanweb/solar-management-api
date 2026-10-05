<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OCR\ComparisonBillOCRService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ComparisonBillOCRController extends Controller
{
    protected ComparisonBillOCRService $ocrService;

    public function __construct(ComparisonBillOCRService $ocrService)
    {
        $this->ocrService = $ocrService;
    }

    /**
     * Process a bill uploaded from the AC/DC Comparison page.
     *
     * This endpoint is intentionally separate from /bills/process-ocr.
     */
    public function process(Request $request): JsonResponse
    {
        $request->validate([
            'bill_file' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'max:10240',
            ],
        ]);

        try {
            $result = $this->ocrService->extract(
                $request->file('bill_file')
            );

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => $result['response'] ?? null,
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Comparison bill OCR completed successfully.',
                'data' => $result['data'],
            ], 200);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => app()->hasDebugModeEnabled()
                    ? $exception->getMessage()
                    : 'Unexpected error occurred.',
            ], 500);
        }
    }
}
