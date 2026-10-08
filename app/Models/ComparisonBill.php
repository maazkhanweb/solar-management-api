<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dedicated AC/DC Comparison bill record.
 *
 * IMPORTANT:
 * This model is completely separate from App\Models\Bill.
 *
 * Existing WAPDA Bill Management is NOT affected.
 */
class ComparisonBill extends Model
{
    protected $table = 'comparison_bills';

    protected $fillable = [

        /*
        |--------------------------------------------------------------------------
        | User / Upload
        |--------------------------------------------------------------------------
        */

        'user_id',
        'bill_file',
        'original_file_name',

        /*
        |--------------------------------------------------------------------------
        | Main OCR Fields
        |--------------------------------------------------------------------------
        */

        'consumer_name',
        'reference_number',
        'consumer_id',
        'area_name',
        'tariff_category',

        /*
        |--------------------------------------------------------------------------
        | Bill Information
        |--------------------------------------------------------------------------
        */

        'bill_month',
        'bill_year',
        'issue_date',
        'due_date',

        'payable_before_due',
        'payable_after_due',
        'current_bill',
        'arrears',

        /*
        |--------------------------------------------------------------------------
        | Meter Information
        |--------------------------------------------------------------------------
        */

        'previous_reading',
        'present_reading',
        'units_consumed',

        /*
        |--------------------------------------------------------------------------
        | Complete OCR JSON
        |--------------------------------------------------------------------------
        |
        | This stores the complete normalized OCR response.
        | Any extra bill fields that are not represented by dedicated
        | database columns are preserved here.
        |
        */

        'ocr_data',

        /*
        |--------------------------------------------------------------------------
        | OCR Status
        |--------------------------------------------------------------------------
        */

        'ocr_status',
        'ocr_confidence',

        /*
        |--------------------------------------------------------------------------
        | Home Analysis
        |--------------------------------------------------------------------------
        */

        'home_analysis_data',
        'home_analysis_result',
        'home_analysis_at',

        /*
        |--------------------------------------------------------------------------
        | Audit
        |--------------------------------------------------------------------------
        */

        'updated_by',
    ];

    protected function casts(): array
    {
        return [

            /*
            | Bill dates / numbers
            */

            'bill_month' => 'integer',
            'bill_year' => 'integer',

            /*
            | Financial values
            */

            'payable_before_due' => 'decimal:2',
            'payable_after_due' => 'decimal:2',
            'current_bill' => 'decimal:2',
            'arrears' => 'decimal:2',

            /*
            | Meter readings
            */

            'previous_reading' => 'decimal:2',
            'present_reading' => 'decimal:2',
            'units_consumed' => 'decimal:2',

            /*
            | OCR
            */

            'ocr_status' => 'boolean',
            'ocr_confidence' => 'decimal:2',

            /*
            | Complete OCR payload
            */

            'ocr_data' => 'array',

            /*
            | Home Analysis
            */

            'home_analysis_data' => 'array',
            'home_analysis_result' => 'array',
            'home_analysis_at' => 'datetime',
        ];
    }

    /**
     * User who uploaded the bill.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }

    /**
     * User/admin who last updated the record.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }
}