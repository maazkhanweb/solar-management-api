<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OCR\ComparisonBillOCRService;
use App\Services\ComparisonBillService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ComparisonBillOCRController extends Controller
{
    public function __construct(
        protected ComparisonBillOCRService $ocrService,
        protected ComparisonBillService $comparisonBillService
    ) {
    }

    /**
     * Process and save a bill for the separate AC/DC Comparison flow.
     *
     * IMPORTANT: This is intentionally independent from the existing
     * /bills/process-ocr WAPDA Bill Management OCR.
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
            $file = $request->file('bill_file');

            $result = $this->ocrService->extract($file);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Comparison bill OCR failed.',
                    'errors' => $result['response'] ?? null,
                ], 400);
            }

            $record = $this->comparisonBillService->createFromOCR(
                $result['data'],
                $file,
                $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Comparison bill OCR completed and saved successfully.',
                'data' => $record,
            ], 201);
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
