<?php

namespace App\Services\OCR\Responses;

class ComparisonBillResponseFormatter
{
    /**
     * Convert Gemini OCR output into a normalized structure.
     *
     * The complete OCR response is preserved in:
     *
     * data.ocr_data
     */
    public static function format(string $response): array
    {
        $response = trim($response);

        /*
        |--------------------------------------------------------------------------
        | Remove markdown fences
        |--------------------------------------------------------------------------
        */

        $response = preg_replace(
            '/```(?:json)?/i',
            '',
            $response
        );

        $response = str_replace(
            '```',
            '',
            $response
        );

        $response = trim($response);

        /*
        |--------------------------------------------------------------------------
        | Extract JSON object
        |--------------------------------------------------------------------------
        */

        $json = self::extractJsonObject(
            $response
        );

        if ($json === null) {

            return [
                'success' => false,

                'message' =>
                    'Gemini returned invalid JSON.',

                'raw_response' =>
                    $response,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Decode
        |--------------------------------------------------------------------------
        */

        try {

            $data = json_decode(
                $json,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

        } catch (\Throwable $exception) {

            /*
            |--------------------------------------------------------------------------
            | Try a second lightweight cleanup
            |--------------------------------------------------------------------------
            */

            $cleaned = self::cleanupJson(
                $json
            );

            try {

                $data = json_decode(
                    $cleaned,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

            } catch (\Throwable) {

                return [
                    'success' => false,

                    'message' =>
                        'Gemini returned invalid JSON.',

                    'raw_response' =>
                        $response,

                    'json_error' =>
                        $exception->getMessage(),
                ];
            }
        }

        if (!is_array($data)) {

            return [
                'success' => false,

                'message' =>
                    'Gemini OCR response is not a JSON object.',

                'raw_response' =>
                    $response,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Normalize
        |--------------------------------------------------------------------------
        */

        $normalized = self::normalize(
            $data
        );

        /*
        |--------------------------------------------------------------------------
        | Confidence
        |--------------------------------------------------------------------------
        */

        $normalized['ocr_confidence'] =
            self::calculateConfidence(
                $normalized
            );

        /*
        |--------------------------------------------------------------------------
        | Status
        |--------------------------------------------------------------------------
        */

        $normalized['ocr_status'] = true;

        /*
        |--------------------------------------------------------------------------
        | Complete response
        |--------------------------------------------------------------------------
        */

        return [

            'success' => true,

            'data' => [

                'consumer_name' =>
                    $normalized['consumer_name'] ?? null,

                'reference_number' =>
                    $normalized['reference_number'] ?? null,

                'consumer_id' =>
                    $normalized['consumer_id'] ?? null,

                'area_name' =>
                    $normalized['area_name'] ?? null,

                'tariff_category' =>
                    $normalized['tariff_category'] ?? null,

                'bill_month' =>
                    $normalized['bill_month'] ?? null,

                'bill_year' =>
                    $normalized['bill_year'] ?? null,

                'issue_date' =>
                    $normalized['issue_date'] ?? null,

                'due_date' =>
                    $normalized['due_date'] ?? null,

                'payable_before_due' =>
                    $normalized['payable_before_due'] ?? null,

                'payable_after_due' =>
                    $normalized['payable_after_due'] ?? null,

                'current_bill' =>
                    $normalized['current_bill'] ?? null,

                'arrears' =>
                    $normalized['arrears'] ?? null,

                'previous_reading' =>
                    $normalized['previous_reading'] ?? null,

                'present_reading' =>
                    $normalized['present_reading'] ?? null,

                'units_consumed' =>
                    $normalized['units_consumed'] ?? null,

                'ocr_status' => true,

                'ocr_confidence' =>
                    $normalized['ocr_confidence'],

                /*
                |--------------------------------------------------------------------------
                | COMPLETE OCR
                |--------------------------------------------------------------------------
                */

                'ocr_data' =>
                    $normalized,
            ],
        ];
    }

    /**
     * Extract the first complete JSON object.
     *
     * Handles braces inside strings.
     */
    protected static function extractJsonObject(
        string $text
    ): ?string {

        $length = strlen($text);

        $start = null;

        $depth = 0;

        $inString = false;

        $escaped = false;

        for (
            $i = 0;
            $i < $length;
            $i++
        ) {

            $char = $text[$i];

            if ($escaped) {

                $escaped = false;

                continue;
            }

            if (
                $char === '\\'
                &&
                $inString
            ) {

                $escaped = true;

                continue;
            }

            if ($char === '"') {

                $inString = !$inString;

                continue;
            }

            if ($inString) {
                continue;
            }

            if ($char === '{') {

                if ($start === null) {
                    $start = $i;
                }

                $depth++;

                continue;
            }

            if ($char === '}') {

                if ($start === null) {
                    continue;
                }

                $depth--;

                if ($depth === 0) {

                    return substr(
                        $text,
                        $start,
                        $i - $start + 1
                    );
                }
            }
        }

        return null;
    }

    /**
     * Basic cleanup for JSON produced by vision models.
     */
    protected static function cleanupJson(
        string $json
    ): string {

        /*
        | Remove trailing commas.
        */

        $json = preg_replace(
            '/,\s*([}\]])/',
            '$1',
            $json
        );

        /*
        | Remove BOM.
        */

        $json = preg_replace(
            '/^\xEF\xBB\xBF/',
            '',
            $json
        );

        return trim($json);
    }

    /**
     * Normalize all known fields while preserving unknown fields.
     */
    protected static function normalize(
        array $data
    ): array {

        /*
        |--------------------------------------------------------------------------
        | Preserve everything first
        |--------------------------------------------------------------------------
        */

        $normalized = $data;

        /*
        |--------------------------------------------------------------------------
        | String fields
        |--------------------------------------------------------------------------
        */

        $stringFields = [

            'utility_provider',
            'bill_type',

            'consumer_name',
            'father_or_husband_name',
            'reference_number',
            'consumer_id',
            'account_number',
            'bill_number',
            'meter_number',

            'area_name',
            'bill_address',
            'tariff_category',
            'connection_type',
            'phase',
            'feeder',
            'subdivision',
            'division',
            'circle',

            'billing_period',
            'connection_date',
            'issue_date',
            'reading_date',
            'due_date',
        ];

        foreach (
            $stringFields as $field
        ) {

            $normalized[$field] =
                self::cleanString(
                    $data[$field] ?? null
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Month/year
        |--------------------------------------------------------------------------
        */

        $normalized['bill_month'] =
            self::cleanMonth(
                $data['bill_month'] ?? null
            );

        $normalized['bill_year'] =
            self::cleanYear(
                $data['bill_year'] ?? null
            );

        /*
        |--------------------------------------------------------------------------
        | Number fields
        |--------------------------------------------------------------------------
        */

        $numberFields = [

            'billing_days',

            'previous_reading',
            'present_reading',
            'units_consumed',
            'meter_multiplying_factor',
            'load',
            'sanctioned_load',
            'connected_load',

            'payable_before_due',
            'payable_after_due',
            'current_bill',
            'arrears',
            'previous_balance',
            'current_charges',
            'electricity_charges',

            'gst',
            'sales_tax',
            'income_tax',
            'tv_fee',
            'njsurcharge',
            'fpa',
            'fuel_price_adjustment',
            'nepra_surcharge',
            'ed',
            'bank_charges',

            'meter_rent',
            'security_deposit',
            'adjustment',
            'discount',
            'late_payment_surcharge',

            'total_amount',
            'total_payable',
        ];

        foreach (
            $numberFields as $field
        ) {

            $normalized[$field] =
                self::cleanNumber(
                    $data[$field] ?? null
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Array/object sections
        |--------------------------------------------------------------------------
        */

        $arrayFields = [

            'meter_readings',
            'reading_history',
            'consumption_history',

            'tariff_details',
            'charge_breakdown',
            'tax_breakdown',
            'payment_history',
            'adjustments',
            'other_charges',
            'bill_items',
            'additional_charges',
        ];

        foreach (
            $arrayFields as $field
        ) {

            if (
                !array_key_exists(
                    $field,
                    $data
                )
            ) {

                $normalized[$field] = [];

                continue;
            }

            $value = $data[$field];

            if (is_array($value)) {

                $normalized[$field] =
                    $value;

            } else {

                $normalized[$field] = [
                    [
                        'value' =>
                            $value,
                    ],
                ];
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Additional information
        |--------------------------------------------------------------------------
        */

        if (
            isset(
                $data['additional_information']
            )
            &&
            is_array(
                $data['additional_information']
            )
        ) {

            $normalized['additional_information'] =
                $data['additional_information'];

        } else {

            $normalized['additional_information'] =
                [];
        }

        return $normalized;
    }

    protected static function calculateConfidence(
        array $data
    ): float {

        $fields = [

            'consumer_name',
            'reference_number',
            'consumer_id',
            'area_name',
            'tariff_category',

            'bill_month',
            'bill_year',

            'issue_date',
            'due_date',

            'previous_reading',
            'present_reading',
            'units_consumed',

            'current_bill',
            'payable_before_due',
            'payable_after_due',
        ];

        $found = 0;

        foreach (
            $fields as $field
        ) {

            if (
                array_key_exists(
                    $field,
                    $data
                )
                &&
                $data[$field] !== null
                &&
                $data[$field] !== ''
            ) {

                $found++;
            }
        }

        if (
            count($fields) === 0
        ) {

            return 0;
        }

        return round(
            (
                $found
                /
                count($fields)
            ) * 100,
            2
        );
    }

    protected static function cleanString(
        mixed $value
    ): ?string {

        if ($value === null) {
            return null;
        }

        if (
            is_array($value)
            ||
            is_object($value)
        ) {

            return null;
        }

        $value = trim(
            (string) $value
        );

        return $value === ''
            ? null
            : $value;
    }

    protected static function cleanNumber(
        mixed $value
    ): ?float {

        if (
            $value === null
            ||
            $value === ''
        ) {

            return null;
        }

        if (
            is_int($value)
            ||
            is_float($value)
            ||
            is_numeric($value)
        ) {

            return (float) $value;
        }

        $value = str_replace(
            ',',
            '',
            (string) $value
        );

        $value = preg_replace(
            '/[^0-9.\-]/',
            '',
            $value
        );

        if (
            $value === ''
            ||
            !is_numeric($value)
        ) {

            return null;
        }

        return (float) $value;
    }

    protected static function cleanMonth(
        mixed $value
    ): ?int {

        if (
            $value === null
            ||
            $value === ''
        ) {

            return null;
        }

        if (is_numeric($value)) {

            $month = (int) $value;

            return (
                $month >= 1
                &&
                $month <= 12
            )
                ? $month
                : null;
        }

        $value = strtolower(
            trim((string) $value)
        );

        $months = [

            'january' => 1,
            'jan' => 1,

            'february' => 2,
            'feb' => 2,

            'march' => 3,
            'mar' => 3,

            'april' => 4,
            'apr' => 4,

            'may' => 5,

            'june' => 6,
            'jun' => 6,

            'july' => 7,
            'jul' => 7,

            'august' => 8,
            'aug' => 8,

            'september' => 9,
            'sep' => 9,
            'sept' => 9,

            'october' => 10,
            'oct' => 10,

            'november' => 11,
            'nov' => 11,

            'december' => 12,
            'dec' => 12,
        ];

        return $months[$value] ?? null;
    }

    protected static function cleanYear(
        mixed $value
    ): ?int {

        if (
            $value === null
            ||
            $value === ''
        ) {

            return null;
        }

        preg_match(
            '/20\d{2}/',
            (string) $value,
            $matches
        );

        if (
            isset($matches[0])
        ) {

            return (int) $matches[0];
        }

        return null;
    }
}