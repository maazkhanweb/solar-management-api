<?php

namespace App\Services;

use App\Models\Area;
use App\Models\Bill;
use App\Models\InventoryItem;
use App\Models\User;
use App\Services\Analysis\BillAnalysisService;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class DashboardService
{
    /**
     * Report Service
     */
    protected ReportService $reportService;

    /**
     * Bill Analysis Service
     */
    protected BillAnalysisService $billAnalysisService;

    /**
     * Constructor
     */
    public function __construct(
        ReportService $reportService,
        BillAnalysisService $billAnalysisService
    ) {
        $this->reportService =
            $reportService;

        $this->billAnalysisService =
            $billAnalysisService;
    }


    /**
     * ==========================================================
     * Get Dashboard Data
     * ==========================================================
     */
    public function getDashboardData(): array
    {
        return [

            'statistics' =>
                $this->getStatistics(),

            /*
             * Month-wise comparison data
             */
            'comparisons' =>
                $this->getMonthlyComparisons(),

        ];
    }


    /**
     * ==========================================================
     * Dashboard Statistics
     * ==========================================================
     */
    private function getStatistics(): array
    {
        $user = Auth::user();


        /*
        |--------------------------------------------------------------------------
        | Administrator
        |--------------------------------------------------------------------------
        */

        if ($user->role === 'Administrator') {

            return [

                'totalUsers' =>
                    User::count(),

                'totalAreas' =>
                    Area::count(),

                'totalInventoryItems' =>
                    InventoryItem::count(),

                'totalAssignedItems' =>
                    InventoryItem::where(
                        'assigned_quantity',
                        '>',
                        0
                    )->count(),

                'totalLowStockItems' =>
                    InventoryItem::whereColumn(
                        'available_quantity',
                        '<=',
                        'minimum_stock'
                    )->count(),

                'totalWapdaBills' =>
                    Bill::count(),

                'totalReports' =>
                    $this->reportService
                        ->getReportCount(),

            ];
        }


        /*
        |--------------------------------------------------------------------------
        | Manager
        |--------------------------------------------------------------------------
        */

        return [

            /*
             * Users
             */
            'totalUsers' =>
                User::where(
                    'area_id',
                    $user->area_id
                )->count(),


            /*
             * Areas
             */
            'totalAreas' =>
                1,


            /*
             * Inventory
             */
            'totalInventoryItems' =>
                InventoryItem::where(
                    'area_id',
                    $user->area_id
                )->count(),


            /*
             * Assigned Items
             */
            'totalAssignedItems' =>
                InventoryItem::where(
                    'area_id',
                    $user->area_id
                )
                ->where(
                    'assigned_quantity',
                    '>',
                    0
                )
                ->count(),


            /*
             * Low Stock
             */
            'totalLowStockItems' =>
                InventoryItem::where(
                    'area_id',
                    $user->area_id
                )
                ->whereColumn(
                    'available_quantity',
                    '<=',
                    'minimum_stock'
                )
                ->count(),


            /*
             * Bills uploaded by Manager
             */
            'totalWapdaBills' =>
                Bill::where(
                    'created_by',
                    $user->id
                )->count(),


            /*
             * Reports
             */
            'totalReports' =>
                $this->reportService
                    ->getReportCount(),

        ];
    }


    /**
     * ==========================================================
     * MONTH-WISE AREA COMPARISONS
     * ==========================================================
     *
     * Structure:
     *
     * [
     *     [
     *         'month' => 8,
     *         'year' => 2026,
     *         'month_name' => 'August 2026',
     *         'comparison_count' => 3,
     *         'comparisons' => [...]
     *     ]
     * ]
     *
     */
    private function getMonthlyComparisons(): array
    {
        $user = Auth::user();

        /*
        |--------------------------------------------------------------------------
        | Bill Query
        |--------------------------------------------------------------------------
        */

        $billQuery = Bill::query();


        /*
        |--------------------------------------------------------------------------
        | Manager Restriction
        |--------------------------------------------------------------------------
        */

        if ($user->role === 'Manager') {

            $billQuery->where(
                'created_by',
                $user->id
            );

        }


        /*
        |--------------------------------------------------------------------------
        | Get Bills
        |--------------------------------------------------------------------------
        |
        | Latest bills first.
        |
        */

        $bills = $billQuery
            ->orderByDesc('bill_year')
            ->orderByDesc('bill_month')
            ->orderByDesc('id')
            ->get();


        /*
        |--------------------------------------------------------------------------
        | No Bills
        |--------------------------------------------------------------------------
        */

        if ($bills->isEmpty()) {

            return [];

        }


        /*
        |--------------------------------------------------------------------------
        | Group Bills By Year + Month
        |--------------------------------------------------------------------------
        |
        | Example:
        |
        | 2026-10
        | 2026-09
        | 2026-08
        |
        */

        $monthlyBills = $bills->groupBy(
            function ($bill) {

                return
                    (int) $bill->bill_year
                    . '-'
                    . str_pad(
                        (int) $bill->bill_month,
                        2,
                        '0',
                        STR_PAD_LEFT
                    );

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Build Monthly Response
        |--------------------------------------------------------------------------
        */

        $months = [];


        foreach ($monthlyBills as $monthKey => $monthBills) {

            /*
            |--------------------------------------------------------------------------
            | First Bill
            |--------------------------------------------------------------------------
            */

            $firstBill =
                $monthBills->first();


            $month =
                (int) $firstBill->bill_month;


            $year =
                (int) $firstBill->bill_year;


            /*
            |--------------------------------------------------------------------------
            | Validate Month
            |--------------------------------------------------------------------------
            */

            if (
                $month < 1 ||
                $month > 12
            ) {

                continue;

            }


            /*
            |--------------------------------------------------------------------------
            | Month Name
            |--------------------------------------------------------------------------
            */

            $monthName =
                Carbon::create(
                    $year,
                    $month,
                    1
                )->format('F Y');


            /*
            |--------------------------------------------------------------------------
            | Avoid Duplicate Area Bills
            |--------------------------------------------------------------------------
            |
            | If an area has multiple bills
            | for the same month/year,
            | use the latest bill.
            |
            */

            $areaBills =
                $monthBills
                    ->groupBy('area_id')
                    ->map(
                        function (
                            $areaBillCollection
                        ) {

                            return
                                $areaBillCollection
                                    ->sortByDesc('id')
                                    ->first();

                        }
                    )
                    ->values();


            /*
            |--------------------------------------------------------------------------
            | Area Comparisons
            |--------------------------------------------------------------------------
            */

            $areaComparisons = [];


            foreach (
                $areaBills
                as $bill
            ) {

                /*
                |--------------------------------------------------------------------------
                | Find Area
                |--------------------------------------------------------------------------
                */

                $area =
                    Area::find(
                        $bill->area_id
                    );


                /*
                |--------------------------------------------------------------------------
                | If Area Doesn't Exist
                |--------------------------------------------------------------------------
                */

                if (!$area) {

                    continue;

                }


                /*
                |--------------------------------------------------------------------------
                | Bill Values
                |--------------------------------------------------------------------------
                */

                $unitsConsumed =
                    (float) (
                        $bill->units_consumed ?? 0
                    );


                $generatedUnits =
                    (float) (
                        $bill->generated_units ?? 0
                    );


                $billAmount =
                    (float) (
                        $bill->bill_amount ?? 0
                    );


                /*
                |--------------------------------------------------------------------------
                | Bill Analysis
                |--------------------------------------------------------------------------
                */

                $analysisResult =
                    $this->billAnalysisService->analyze(

                        $unitsConsumed,

                        $generatedUnits,

                        $billAmount

                    );


                $analysisData =
                    $analysisResult['analysis']
                    ?? [];


                /*
                |--------------------------------------------------------------------------
                | Signed Difference
                |--------------------------------------------------------------------------
                |
                | Positive = Solar Generation > WAPDA Consumption
                |
                | Negative = Solar Generation < WAPDA Consumption
                |
                */

                $signedDifference =
                    $generatedUnits -
                    $unitsConsumed;


                /*
                |--------------------------------------------------------------------------
                | Benefit / Loss
                |--------------------------------------------------------------------------
                */

                if (
                    $signedDifference >= 0
                ) {

                    $result =
                        'Benefit';


                    $benefitUnits =
                        $signedDifference;


                    $lossUnits =
                        0;

                } else {

                    $result =
                        'Loss';


                    $benefitUnits =
                        0;


                    $lossUnits =
                        abs(
                            $signedDifference
                        );

                }


                /*
                |--------------------------------------------------------------------------
                | Area Comparison Object
                |--------------------------------------------------------------------------
                */

                $areaComparisons[] = [

                    'area' => [

                        'id' =>
                            $area->id,

                        'name' =>
                            $area->area_name,

                        'status' =>
                            $area->status,

                    ],


                    'has_bill' =>
                        true,


                    'bill' => [

                        'id' =>
                            $bill->id,

                        'month' =>
                            $month,

                        'year' =>
                            $year,

                        'month_name' =>
                            $monthName,

                        'units_consumed' =>
                            round(
                                $unitsConsumed,
                                2
                            ),

                        'generated_units' =>
                            round(
                                $generatedUnits,
                                2
                            ),

                        'bill_amount' =>
                            round(
                                $billAmount,
                                2
                            ),

                    ],


                    'analysis' =>
                        $analysisData,


                    'comparison' => [

                        'result' =>
                            $result,

                        'difference_units' =>
                            round(
                                $signedDifference,
                                2
                            ),

                        'benefit_units' =>
                            round(
                                $benefitUnits,
                                2
                            ),

                        'loss_units' =>
                            round(
                                $lossUnits,
                                2
                            ),

                    ],

                ];

            }


            /*
            |--------------------------------------------------------------------------
            | Only Add Month If It Has Comparison Data
            |--------------------------------------------------------------------------
            */

            if (
                empty(
                    $areaComparisons
                )
            ) {

                continue;

            }


            /*
            |--------------------------------------------------------------------------
            | Add Month
            |--------------------------------------------------------------------------
            */

            $months[] = [

                'month' =>
                    $month,

                'year' =>
                    $year,

                'month_name' =>
                    $monthName,

                'comparison_count' =>
                    count(
                        $areaComparisons
                    ),

                'comparisons' =>
                    $areaComparisons,

            ];

        }


        /*
        |--------------------------------------------------------------------------
        | Sort Months
        |--------------------------------------------------------------------------
        |
        | Latest month first.
        |
        */

        usort(
            $months,
            function (
                $a,
                $b
            ) {

                if (
                    $a['year'] ===
                    $b['year']
                ) {

                    return
                        $b['month'] -
                        $a['month'];

                }

                return
                    $b['year'] -
                    $a['year'];

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Return Monthly Comparisons
        |--------------------------------------------------------------------------
        */

        return $months;
    }


    /**
     * ==========================================================
     * Solar Production Chart
     * ==========================================================
     */
    private function getProductionChart(): array
    {
        $user = Auth::user();

        $query =
            Bill::query();


        /*
        |--------------------------------------------------------------------------
        | Manager
        |--------------------------------------------------------------------------
        */

        if (
            $user->role ===
            'Manager'
        ) {

            $query->where(
                'created_by',
                $user->id
            );

        }


        $rows =
            $query
                ->selectRaw(
                    "
                    bill_month,
                    COALESCE(
                        SUM(generated_units),
                        0
                    ) as production
                    "
                )
                ->groupBy(
                    'bill_month'
                )
                ->orderBy(
                    'bill_month'
                )
                ->get();


        $months = [

            1 => 'Jan',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Apr',
            5 => 'May',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Aug',
            9 => 'Sep',
            10 => 'Oct',
            11 => 'Nov',
            12 => 'Dec',

        ];


        $chart = [];


        foreach (
            $months
            as $number => $name
        ) {

            $production =
                optional(
                    $rows->firstWhere(
                        'bill_month',
                        $number
                    )
                )->production
                ?? 0;


            $chart[] = [

                'month' =>
                    $name,

                'production' =>
                    (float) $production,

            ];

        }


        return $chart;
    }


    /**
     * ==========================================================
     * Battery Health Chart
     * ==========================================================
     */
    private function getBatteryHealthChart(): array
    {
        $user =
            Auth::user();


        $query =
            InventoryItem::query()
                ->where(
                    'item_type',
                    'battery'
                );


        /*
        |--------------------------------------------------------------------------
        | Manager
        |--------------------------------------------------------------------------
        */

        if (
            $user->role ===
            'Manager'
        ) {

            $query->where(
                'area_id',
                $user->area_id
            );

        }


        $batteries =
            $query
                ->orderBy(
                    'item_name'
                )
                ->get();


        $chart = [];


        foreach (
            $batteries
            as $battery
        ) {

            $chart[] = [

                'battery' =>
                    $battery->item_name,

                'health' =>
                    (float)
                    $battery->battery_health,

            ];

        }


        return $chart;
    }


    /**
     * ==========================================================
     * WAPDA Bill Trend
     * ==========================================================
     */
    private function getWapdaBillTrend(): array
    {
        $user =
            Auth::user();


        $query =
            Bill::query();


        /*
        |--------------------------------------------------------------------------
        | Manager
        |--------------------------------------------------------------------------
        */

        if (
            $user->role ===
            'Manager'
        ) {

            $query->where(
                'created_by',
                $user->id
            );

        }


        $rows =
            $query
                ->selectRaw(
                    "
                    bill_month,
                    COALESCE(
                        SUM(bill_amount),
                        0
                    ) as total_bill
                    "
                )
                ->groupBy(
                    'bill_month'
                )
                ->orderBy(
                    'bill_month'
                )
                ->get();


        $months = [

            1 => 'Jan',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Apr',
            5 => 'May',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Aug',
            9 => 'Sep',
            10 => 'Oct',
            11 => 'Nov',
            12 => 'Dec',

        ];


        $chart = [];


        foreach (
            $months
            as $number => $name
        ) {

            $bill =
                optional(
                    $rows->firstWhere(
                        'bill_month',
                        $number
                    )
                )->total_bill
                ?? 0;


            $chart[] = [

                'month' =>
                    $name,

                'bill' =>
                    (float) $bill,

            ];

        }


        return $chart;
    }


    /**
     * ==========================================================
     * AI Performance
     * ==========================================================
     */
    private function getAiPerformance(): array
    {
        $user =
            Auth::user();


        $query =
            Bill::query();


        /*
        |--------------------------------------------------------------------------
        | Manager
        |--------------------------------------------------------------------------
        */

        if (
            $user->role ===
            'Manager'
        ) {

            $query->where(
                'created_by',
                $user->id
            );

        }


        $bills =
            $query->get();


        $excellent = 0;

        $good = 0;

        $average = 0;

        $poor = 0;


        foreach (
            $bills
            as $bill
        ) {

            $confidence =
                (float)
                $bill->ocr_confidence;


            if (
                $confidence >= 95
            ) {

                $excellent++;

            } elseif (
                $confidence >= 85
            ) {

                $good++;

            } elseif (
                $confidence >= 70
            ) {

                $average++;

            } else {

                $poor++;

            }

        }


        return [

            [

                'name' =>
                    'Excellent',

                'value' =>
                    $excellent,

            ],

            [

                'name' =>
                    'Good',

                'value' =>
                    $good,

            ],

            [

                'name' =>
                    'Average',

                'value' =>
                    $average,

            ],

            [

                'name' =>
                    'Poor',

                'value' =>
                    $poor,

            ],

        ];
    }
}