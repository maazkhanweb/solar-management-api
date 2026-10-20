<?php

namespace App\Services;

use App\Models\User;
use App\Services\Analysis\BillAnalysisService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardService
{
    protected ReportService $reportService;

    protected BillAnalysisService $billAnalysisService;

    public function __construct(
        ReportService $reportService,
        BillAnalysisService $billAnalysisService
    ) {
        $this->reportService = $reportService;
        $this->billAnalysisService = $billAnalysisService;
    }

    /**
     * Get dashboard statistics and monthly comparisons.
     */
    public function getDashboardData(): array
    {
        $user = Auth::user();

        if (!$user instanceof User) {
            abort(401, 'Unauthenticated.');
        }

        $dashboardData = $this->getDashboardQueryData($user);

        return [
            'statistics' => $this->getStatistics(
                $dashboardData->statistics,
                $user
            ),
            'comparisons' => $this->getMonthlyComparisons(
                collect($dashboardData->bills)
            ),
        ];
    }

    /**
     * Fetch dashboard data in one PostgreSQL round trip.
     *
     * Optimization:
     * Return only the latest bill for each area/month/year.
     * The total bill count still includes all bills.
     */
    private function getDashboardQueryData(User $user): object
    {
        if ($user->role === 'Administrator') {
            $dashboardData = DB::selectOne(<<<'SQL'
WITH statistics AS (
    SELECT
        (SELECT COUNT(*) FROM users) AS total_users,
        (SELECT COUNT(*) FROM areas) AS total_areas,
        inventory_statistics.total_inventory_items,
        inventory_statistics.total_assigned_items,
        inventory_statistics.total_low_stock_items,
        (SELECT COUNT(*) FROM bills) AS total_wapda_bills
    FROM (
        SELECT
            COUNT(*) AS total_inventory_items,
            COALESCE(
                SUM(
                    CASE
                        WHEN assigned_quantity > 0 THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS total_assigned_items,
            COALESCE(
                SUM(
                    CASE
                        WHEN available_quantity <= minimum_stock THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS total_low_stock_items
        FROM inventory_items
    ) AS inventory_statistics
)
SELECT
    row_to_json(statistics)::text AS statistics,
    COALESCE(
        (
            SELECT json_agg(
                json_build_object(
                    'id', latest_bills.id,
                    'area_id', latest_bills.area_id,
                    'bill_month', latest_bills.bill_month,
                    'bill_year', latest_bills.bill_year,
                    'units_consumed', latest_bills.units_consumed,
                    'generated_units', latest_bills.generated_units,
                    'bill_amount', latest_bills.bill_amount,
                    'dashboard_area_id', areas.id,
                    'dashboard_area_name', areas.area_name,
                    'dashboard_area_status', areas.status
                )
                ORDER BY
                    latest_bills.bill_year DESC,
                    latest_bills.bill_month DESC,
                    latest_bills.id DESC
            )
            FROM (
                SELECT DISTINCT ON (
                    area_id,
                    bill_year,
                    bill_month
                )
                    id,
                    area_id,
                    bill_month,
                    bill_year,
                    units_consumed,
                    generated_units,
                    bill_amount
                FROM bills
                ORDER BY
                    area_id,
                    bill_year,
                    bill_month,
                    id DESC
            ) AS latest_bills
            LEFT JOIN areas
                ON latest_bills.area_id = areas.id
        ),
        '[]'::json
    )::text AS bills
FROM statistics
SQL);
        } else {
            $dashboardQuery = <<<'SQL'
WITH statistics AS (
    SELECT
        (
            SELECT COUNT(*)
            FROM users
            WHERE area_id IS NOT DISTINCT FROM ?
        ) AS total_users,
        1 AS total_areas,
        inventory_statistics.total_inventory_items,
        inventory_statistics.total_assigned_items,
        inventory_statistics.total_low_stock_items,
        (
            SELECT COUNT(*)
            FROM bills
            WHERE created_by = ?
        ) AS total_wapda_bills
    FROM (
        SELECT
            COUNT(*) AS total_inventory_items,
            COALESCE(
                SUM(
                    CASE
                        WHEN assigned_quantity > 0 THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS total_assigned_items,
            COALESCE(
                SUM(
                    CASE
                        WHEN available_quantity <= minimum_stock THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS total_low_stock_items
        FROM inventory_items
        WHERE area_id IS NOT DISTINCT FROM ?
    ) AS inventory_statistics
)
SELECT
    row_to_json(statistics)::text AS statistics,
    COALESCE(
        (
            SELECT json_agg(
                json_build_object(
                    'id', latest_bills.id,
                    'area_id', latest_bills.area_id,
                    'bill_month', latest_bills.bill_month,
                    'bill_year', latest_bills.bill_year,
                    'units_consumed', latest_bills.units_consumed,
                    'generated_units', latest_bills.generated_units,
                    'bill_amount', latest_bills.bill_amount,
                    'dashboard_area_id', areas.id,
                    'dashboard_area_name', areas.area_name,
                    'dashboard_area_status', areas.status
                )
                ORDER BY
                    latest_bills.bill_year DESC,
                    latest_bills.bill_month DESC,
                    latest_bills.id DESC
            )
            FROM (
                SELECT DISTINCT ON (
                    area_id,
                    bill_year,
                    bill_month
                )
                    id,
                    area_id,
                    bill_month,
                    bill_year,
                    units_consumed,
                    generated_units,
                    bill_amount
                FROM bills
                WHERE created_by = ?
                ORDER BY
                    area_id,
                    bill_year,
                    bill_month,
                    id DESC
            ) AS latest_bills
            LEFT JOIN areas
                ON latest_bills.area_id = areas.id
        ),
        '[]'::json
    )::text AS bills
FROM statistics
SQL;

            $dashboardData = DB::selectOne(
                $dashboardQuery,
                [
                    $user->area_id,
                    $user->id,
                    $user->area_id,
                    $user->id,
                ]
            );
        }

        return (object) [
            'statistics' => json_decode(
                $dashboardData->statistics ?? '{}'
            ),
            'bills' => json_decode(
                $dashboardData->bills ?? '[]'
            ),
        ];
    }

    /**
     * Prepare dashboard statistics.
     */
    private function getStatistics(
        object $statistics,
        User $user
    ): array {
        return [
            'totalUsers' => (int) (
                $statistics->total_users ?? 0
            ),

            'totalAreas' => $user->role === 'Administrator'
                ? (int) ($statistics->total_areas ?? 0)
                : 1,

            'totalInventoryItems' => (int) (
                $statistics->total_inventory_items ?? 0
            ),

            'totalAssignedItems' => (int) (
                $statistics->total_assigned_items ?? 0
            ),

            'totalLowStockItems' => (int) (
                $statistics->total_low_stock_items ?? 0
            ),

            'totalWapdaBills' => (int) (
                $statistics->total_wapda_bills ?? 0
            ),

            'totalReports' => $this->reportService
                ->getReportCount(),
        ];
    }

    /**
     * Build monthly area comparisons.
     *
     * The SQL query already returns the latest bill for each
     * area/month/year, so we avoid repeatedly sorting old bills.
     */
    private function getMonthlyComparisons($bills): array
    {
        if ($bills->isEmpty()) {
            return [];
        }

        $monthlyBills = $bills->groupBy(
            function ($bill) {
                return (int) $bill->bill_year
                    . '-'
                    . str_pad(
                        (int) $bill->bill_month,
                        2,
                        '0',
                        STR_PAD_LEFT
                    );
            }
        );

        $months = [];

        foreach ($monthlyBills as $monthBills) {
            $firstBill = $monthBills->first();

            $month = (int) $firstBill->bill_month;
            $year = (int) $firstBill->bill_year;

            if ($month < 1 || $month > 12 || $year < 1) {
                continue;
            }

            $monthName = Carbon::create(
                $year,
                $month,
                1
            )->format('F Y');

            $areaComparisons = [];

            foreach ($monthBills as $bill) {
                if ($bill->dashboard_area_id === null) {
                    continue;
                }

                $unitsConsumed = (float) (
                    $bill->units_consumed ?? 0
                );

                $generatedUnits = (float) (
                    $bill->generated_units ?? 0
                );

                $billAmount = (float) (
                    $bill->bill_amount ?? 0
                );

                $analysisResult = $this->billAnalysisService->analyze(
                    $unitsConsumed,
                    $generatedUnits,
                    $billAmount
                );

                $analysisData = $analysisResult['analysis'] ?? [];

                $signedDifference = $generatedUnits - $unitsConsumed;

                if ($signedDifference >= 0) {
                    $result = 'Benefit';
                    $benefitUnits = $signedDifference;
                    $lossUnits = 0;
                } else {
                    $result = 'Loss';
                    $benefitUnits = 0;
                    $lossUnits = abs($signedDifference);
                }

                $areaComparisons[] = [
                    'area' => [
                        'id' => (int) $bill->dashboard_area_id,
                        'name' => $bill->dashboard_area_name,
                        'status' => $bill->dashboard_area_status,
                    ],

                    'has_bill' => true,

                    'bill' => [
                        'id' => $bill->id,
                        'month' => $month,
                        'year' => $year,
                        'month_name' => $monthName,
                        'units_consumed' => round(
                            $unitsConsumed,
                            2
                        ),
                        'generated_units' => round(
                            $generatedUnits,
                            2
                        ),
                        'bill_amount' => round(
                            $billAmount,
                            2
                        ),
                    ],

                    'analysis' => $analysisData,

                    'comparison' => [
                        'result' => $result,
                        'difference_units' => round(
                            $signedDifference,
                            2
                        ),
                        'benefit_units' => round(
                            $benefitUnits,
                            2
                        ),
                        'loss_units' => round(
                            $lossUnits,
                            2
                        ),
                    ],
                ];
            }

            if (empty($areaComparisons)) {
                continue;
            }

            $months[] = [
                'month' => $month,
                'year' => $year,
                'month_name' => $monthName,
                'comparison_count' => count($areaComparisons),
                'comparisons' => $areaComparisons,
            ];
        }

        usort(
            $months,
            function ($a, $b) {
                if ($a['year'] === $b['year']) {
                    return $b['month'] - $a['month'];
                }

                return $b['year'] - $a['year'];
            }
        );

        return $months;
    }
}