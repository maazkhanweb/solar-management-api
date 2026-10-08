<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ComparisonBill;
use App\Services\ComparisonBillService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ComparisonBillController extends Controller
{
    public function __construct(
        protected ComparisonBillService $comparisonBillService
    ) {
    }

    /**
     * Get comparison bills.
     *
     * Admin:
     * all records.
     *
     * User:
     * own records.
     */
    public function index(
        Request $request
    ): JsonResponse {

        return response()->json([
            'success' => true,

            'message' =>
                'Comparison bills fetched successfully.',

            'data' =>
                $this->comparisonBillService
                    ->getAll(
                        $request->all()
                    ),
        ]);
    }

    /**
     * Get one complete comparison bill.
     *
     * Includes:
     * - main OCR fields
     * - complete ocr_data
     * - home analysis
     * - user information
     */
    public function show(
        ComparisonBill $comparisonBill
    ): JsonResponse {

        try {

            return response()->json([
                'success' => true,

                'message' =>
                    'Comparison bill fetched successfully.',

                'data' =>
                    $this->comparisonBillService
                        ->getOne(
                            $comparisonBill
                        ),
            ]);

        } catch (Throwable $exception) {

            report($exception);

            return response()->json([
                'success' => false,

                'message' =>
                    $exception->getMessage(),

            ], $this->getExceptionStatus(
                $exception
            ));
        }
    }

    /**
     * Admin-only update.
     */
    public function update(
        Request $request,
        ComparisonBill $comparisonBill
    ): JsonResponse {

        $validated = $request->validate([

            /*
            |--------------------------------------------------------------------------
            | Main OCR fields
            |--------------------------------------------------------------------------
            */

            'consumer_name' =>
                ['nullable', 'string', 'max:255'],

            'reference_number' =>
                ['nullable', 'string', 'max:255'],

            'consumer_id' =>
                ['nullable', 'string', 'max:255'],

            'area_name' =>
                ['nullable', 'string', 'max:255'],

            'tariff_category' =>
                ['nullable', 'string', 'max:255'],

            /*
            |--------------------------------------------------------------------------
            | Bill
            |--------------------------------------------------------------------------
            */

            'bill_month' =>
                ['nullable', 'integer', 'between:1,12'],

            'bill_year' =>
                ['nullable', 'integer', 'between:2000,2100'],

            'issue_date' =>
                ['nullable', 'string', 'max:100'],

            'due_date' =>
                ['nullable', 'string', 'max:100'],

            'payable_before_due' =>
                ['nullable', 'numeric'],

            'payable_after_due' =>
                ['nullable', 'numeric'],

            'current_bill' =>
                ['nullable', 'numeric'],

            'arrears' =>
                ['nullable', 'numeric'],

            /*
            |--------------------------------------------------------------------------
            | Meter
            |--------------------------------------------------------------------------
            */

            'previous_reading' =>
                ['nullable', 'numeric'],

            'present_reading' =>
                ['nullable', 'numeric'],

            'units_consumed' =>
                ['nullable', 'numeric'],

            /*
            |--------------------------------------------------------------------------
            | Complete OCR
            |--------------------------------------------------------------------------
            */

            'ocr_data' =>
                ['nullable', 'array'],
        ]);

        try {

            return response()->json([

                'success' => true,

                'message' =>
                    'Comparison bill updated successfully.',

                'data' =>
                    $this->comparisonBillService
                        ->update(
                            $comparisonBill,
                            $validated
                        ),
            ]);

        } catch (Throwable $exception) {

            report($exception);

            return response()->json([

                'success' => false,

                'message' =>
                    $exception->getMessage(),

            ], $this->getExceptionStatus(
                $exception
            ));
        }
    }

    /**
     * Save Home Analysis against a bill.
     *
     * This is the SAME comparison_bills record.
     */
    public function homeAnalysis(
        Request $request,
        ComparisonBill $comparisonBill
    ): JsonResponse {

        $validated = $request->validate([

            /*
            | Preferred format
            */

            'home_data' =>
                ['nullable', 'array'],

            'home_result' =>
                ['nullable', 'array'],

            /*
            | Compatibility format
            */

            'home_analysis' =>
                ['nullable', 'array'],
        ]);

        try {

            $homeData =
                $validated['home_data']
                ?? null;

            $homeResult =
                $validated['home_result']
                ?? null;

            /*
            |--------------------------------------------------------------------------
            | Compatibility
            |--------------------------------------------------------------------------
            |
            | If frontend sends:
            |
            | {
            |    home_analysis: {...}
            | }
            |
            | we save it as both input/result.
            |
            */

            if (
                $homeData === null
                &&
                $homeResult === null
                &&
                isset(
                    $validated['home_analysis']
                )
            ) {

                $homeData =
                    $validated['home_analysis'];

                $homeResult =
                    $validated['home_analysis'];
            }

            if (
                !is_array($homeData)
                ||
                !is_array($homeResult)
            ) {

                return response()->json([

                    'success' => false,

                    'message' =>
                        'Home analysis data and result are required.',

                ], 422);
            }

            $record =
                $this->comparisonBillService
                    ->saveHomeAnalysis(
                        $comparisonBill,
                        $homeData,
                        $homeResult
                    );

            return response()->json([

                'success' => true,

                'message' =>
                    'Home analysis saved successfully.',

                'data' => $record,

            ]);

        } catch (Throwable $exception) {

            report($exception);

            return response()->json([

                'success' => false,

                'message' =>
                    $exception->getMessage(),

            ], $this->getExceptionStatus(
                $exception
            ));
        }
    }

    /**
     * Admin-only delete.
     */
    public function destroy(
        ComparisonBill $comparisonBill
    ): JsonResponse {

        try {

            $this->comparisonBillService
                ->delete(
                    $comparisonBill
                );

            return response()->json([

                'success' => true,

                'message' =>
                    'Comparison bill deleted successfully.',

            ]);

        } catch (Throwable $exception) {

            report($exception);

            return response()->json([

                'success' => false,

                'message' =>
                    $exception->getMessage(),

            ], $this->getExceptionStatus(
                $exception
            ));
        }
    }

    /**
     * Convert exception code to safe HTTP status.
     */
    protected function getExceptionStatus(
        Throwable $exception
    ): int {

        $code =
            (int) $exception->getCode();

        if (
            $code >= 400
            &&
            $code <= 599
        ) {

            return $code;
        }

        return 500;
    }
}