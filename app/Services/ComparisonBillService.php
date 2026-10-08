<?php

namespace App\Services;

use App\Models\ComparisonBill;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ComparisonBillService
{
    /**
     * Get comparison bills.
     *
     * Administrator:
     * - sees all records.
     *
     * Normal user:
     * - sees only own records.
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $user = Auth::user();

        $query = ComparisonBill::query()
            ->with([
                'user:id,name,email',
                'updatedBy:id,name,email',
            ]);

        if ($user?->role !== 'Administrator') {
            $query->where(
                'user_id',
                $user?->id
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        if (!empty($filters['search'])) {

            $search = trim(
                $filters['search']
            );

            $query->where(function ($q) use ($search) {

                $q->where(
                    'consumer_name',
                    'like',
                    "%{$search}%"
                )

                ->orWhere(
                    'reference_number',
                    'like',
                    "%{$search}%"
                )

                ->orWhere(
                    'consumer_id',
                    'like',
                    "%{$search}%"
                )

                ->orWhere(
                    'area_name',
                    'like',
                    "%{$search}%"
                )

                ->orWhereHas(
                    'user',
                    function ($userQuery) use ($search) {

                        $userQuery
                            ->where(
                                'email',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'name',
                                'like',
                                "%{$search}%"
                            );
                    }
                );
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Month / Year filters
        |--------------------------------------------------------------------------
        */

        if (!empty($filters['bill_month'])) {

            $query->where(
                'bill_month',
                $filters['bill_month']
            );
        }

        if (!empty($filters['bill_year'])) {

            $query->where(
                'bill_year',
                $filters['bill_year']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Pagination
        |--------------------------------------------------------------------------
        */

        $perPage = max(
            1,
            min(
                (int) (
                    $filters['per_page']
                    ?? 10
                ),
                100
            )
        );

        return $query
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get one bill.
     */
    public function getOne(
        ComparisonBill $comparisonBill
    ): ComparisonBill {

        $this->ensureCanView(
            $comparisonBill
        );

        return $comparisonBill->load([
            'user:id,name,email',
            'updatedBy:id,name,email',
        ]);
    }

    /**
     * Create bill record after OCR.
     *
     * COMPLETE OCR JSON is saved in ocr_data.
     */
    public function createFromOCR(
        array $ocrData,
        UploadedFile $file,
        User $user
    ): ComparisonBill {

        return DB::transaction(
            function () use (
                $ocrData,
                $file,
                $user
            ) {

                /*
                |--------------------------------------------------------------------------
                | Store original uploaded file
                |--------------------------------------------------------------------------
                */

                $storedPath = $file->store(
                    'comparison-bills',
                    'public'
                );

                /*
                |--------------------------------------------------------------------------
                | Create database record
                |--------------------------------------------------------------------------
                */

                $record = ComparisonBill::create([

                    'user_id' =>
                        $user->id,

                    'bill_file' =>
                        $storedPath,

                    'original_file_name' =>
                        $file->getClientOriginalName(),

                    /*
                    | Main OCR fields
                    */

                    'consumer_name' =>
                        $ocrData['consumer_name']
                        ?? null,

                    'reference_number' =>
                        $ocrData['reference_number']
                        ?? null,

                    'consumer_id' =>
                        $ocrData['consumer_id']
                        ?? null,

                    'area_name' =>
                        $ocrData['area_name']
                        ?? null,

                    'tariff_category' =>
                        $ocrData['tariff_category']
                        ?? null,

                    /*
                    | Bill information
                    */

                    'bill_month' =>
                        $ocrData['bill_month']
                        ?? null,

                    'bill_year' =>
                        $ocrData['bill_year']
                        ?? null,

                    'issue_date' =>
                        $ocrData['issue_date']
                        ?? null,

                    'due_date' =>
                        $ocrData['due_date']
                        ?? null,

                    'payable_before_due' =>
                        $ocrData['payable_before_due']
                        ?? null,

                    'payable_after_due' =>
                        $ocrData['payable_after_due']
                        ?? null,

                    'current_bill' =>
                        $ocrData['current_bill']
                        ?? null,

                    'arrears' =>
                        $ocrData['arrears']
                        ?? null,

                    /*
                    | Meter
                    */

                    'previous_reading' =>
                        $ocrData['previous_reading']
                        ?? null,

                    'present_reading' =>
                        $ocrData['present_reading']
                        ?? null,

                    'units_consumed' =>
                        $ocrData['units_consumed']
                        ?? null,

                    /*
                    | Complete OCR
                    */

                    'ocr_data' =>
                        $ocrData['ocr_data']
                        ?? $ocrData,

                    /*
                    | OCR status
                    */

                    'ocr_status' =>
                        (bool) (
                            $ocrData['ocr_status']
                            ?? true
                        ),

                    'ocr_confidence' =>
                        $ocrData['ocr_confidence']
                        ?? null,
                ]);

                return $record->load([
                    'user:id,name,email',
                ]);
            }
        );
    }

    /**
     * Admin-only update.
     */
    public function update(
        ComparisonBill $comparisonBill,
        array $data
    ): ComparisonBill {

        $this->ensureAdmin();

        $data['updated_by'] =
            Auth::id();

        $comparisonBill->update(
            $data
        );

        return $comparisonBill->fresh([
            'user:id,name,email',
            'updatedBy:id,name,email',
        ]);
    }

    /**
     * Save Home Analysis against the SAME bill.
     *
     * Normal user:
     * - can save analysis for own bill.
     *
     * Admin:
     * - can save for any bill.
     */
    public function saveHomeAnalysis(
        ComparisonBill $comparisonBill,
        array $homeData,
        array $homeResult
    ): ComparisonBill {

        $this->ensureCanView(
            $comparisonBill
        );

        $comparisonBill->update([

            'home_analysis_data' =>
                $homeData,

            'home_analysis_result' =>
                $homeResult,

            'home_analysis_at' =>
                now(),
        ]);

        return $comparisonBill->fresh([
            'user:id,name,email',
            'updatedBy:id,name,email',
        ]);
    }

    /**
     * Delete comparison bill.
     *
     * Admin only.
     */
    public function delete(
        ComparisonBill $comparisonBill
    ): void {

        $this->ensureAdmin();

        DB::transaction(
            function () use (
                $comparisonBill
            ) {

                if (
                    $comparisonBill->bill_file
                ) {

                    Storage::disk('public')
                        ->delete(
                            $comparisonBill->bill_file
                        );
                }

                $comparisonBill->delete();
            }
        );
    }

    /**
     * Admin permission.
     */
    protected function ensureAdmin(): void
    {
        if (
            Auth::user()?->role
            !== 'Administrator'
        ) {

            throw new AccessDeniedHttpException(
                'Only Administrator can perform this action.'
            );
        }
    }

    /**
     * View permission.
     */
    protected function ensureCanView(
        ComparisonBill $comparisonBill
    ): void {

        $user = Auth::user();

        if (
            $user?->role !== 'Administrator'
            &&
            (int) $comparisonBill->user_id
                !== (int) $user?->id
        ) {

            throw new AccessDeniedHttpException(
                'You are not allowed to access this comparison bill.'
            );
        }
    }
}