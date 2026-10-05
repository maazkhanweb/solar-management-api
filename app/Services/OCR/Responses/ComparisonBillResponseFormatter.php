<?php

namespace App\Services\OCR\Responses;

class ComparisonBillResponseFormatter
{
    /**
     * Formats the dedicated AC/DC Comparison OCR response.
     *
     * This formatter is intentionally separate from BillResponseFormatter
     * so the existing WAPDA Bill Management OCR remains unchanged.
     */
    public static function format(string $response): array
    {
        $response = trim($response);

        $response = preg_replace('/^```json/i', '', $response);
        $response = preg_replace('/^```/i', '', $response);
        $response = preg_replace('/```$/', '', $response);
        $response = trim($response);

        $data = json_decode($response, true);

        if (
            json_last_error() !== JSON_ERROR_NONE ||
            !is_array($data)
        ) {
            return [
                'success' => false,
                'message' => 'Gemini returned invalid JSON.',
                'raw_response' => $response,
            ];
        }

        return [
            'success' => true,
            'data' => [
                'consumer_name' => self::cleanString($data['consumer_name'] ?? null),
                'reference_number' => self::cleanString($data['reference_number'] ?? null),
                'consumer_id' => self::cleanString($data['consumer_id'] ?? null),
                'area_name' => self::cleanString($data['area_name'] ?? null),
                'tariff_category' => self::cleanString($data['tariff_category'] ?? null),

                'bill_month' => self::cleanInteger($data['bill_month'] ?? null),
                'bill_year' => self::cleanInteger($data['bill_year'] ?? null),
                'issue_date' => self::cleanDate($data['issue_date'] ?? null),
                'due_date' => self::cleanDate($data['due_date'] ?? null),
                'payable_before_due' => self::cleanNumber($data['payable_before_due'] ?? null),
                'payable_after_due' => self::cleanNumber($data['payable_after_due'] ?? null),
                'current_bill' => self::cleanNumber($data['current_bill'] ?? null),
                'arrears' => self::cleanNumber($data['arrears'] ?? null),

                'previous_reading' => self::cleanNumber($data['previous_reading'] ?? null),
                'present_reading' => self::cleanNumber($data['present_reading'] ?? null),
                'units_consumed' => self::cleanNumber($data['units_consumed'] ?? null),

                'ocr_status' => true,
                'ocr_confidence' => self::calculateConfidence($data),
            ],
        ];
    }

    protected static function calculateConfidence(array $data): float
    {
        $score = 0;

        $weightedFields = [
            'consumer_name' => 8,
            'reference_number' => 8,
            'consumer_id' => 7,
            'area_name' => 5,
            'tariff_category' => 5,
            'bill_month' => 5,
            'bill_year' => 5,
            'issue_date' => 5,
            'due_date' => 7,
            'payable_before_due' => 7,
            'payable_after_due' => 7,
            'current_bill' => 7,
            'arrears' => 5,
            'previous_reading' => 5,
            'present_reading' => 7,
            'units_consumed' => 7,
        ];

        foreach ($weightedFields as $field => $weight) {
            if (
                isset($data[$field]) &&
                $data[$field] !== null &&
                $data[$field] !== ''
            ) {
                $score += $weight;
            }
        }

        return (float) $score;
    }

    protected static function cleanString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected static function cleanInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = preg_replace('/[^0-9]/', '', (string) $value);

        return $value === '' ? null : (int) $value;
    }

    protected static function cleanNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = str_replace(',', '', (string) $value);
        $value = preg_replace('/[^0-9.\-]/', '', $value);

        if ($value === '' || !is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    protected static function cleanDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
