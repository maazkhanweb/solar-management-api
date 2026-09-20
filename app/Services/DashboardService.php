<?php

namespace App\Services;

use App\Models\Area;
use App\Models\Bill;
use App\Models\InventoryItem;
use App\Models\User;
use App\Services\Analysis\BillAnalysisService;
use Illuminate\Support\Facades\Auth;

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
        $this->reportService = $reportService;

        $this->billAnalysisService = $billAnalysisService;
    }

    /**
     * Get Dashboard Data
     */
    public function getDashboardData(): array
    {
        return [

            'statistics' => $this->getStatistics(),

            'production' => $this->getProductionChart(),

            'batteryHealth' => $this->getBatteryHealthChart(),

            'wapdaTrend' => $this->getWapdaBillTrend(),

            'aiPerformance' => $this->getAiPerformance(),

            'comparisons' => $this->getAreaComparisons(),

        ];
    }

    /**
     * Dashboard Statistics
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

                'totalUsers' => User::count(),

                'totalAreas' => Area::count(),

                'totalInventoryItems' => InventoryItem::count(),

                'totalAssignedItems' => InventoryItem::where(
                    'assigned_quantity',
                    '>',
                    0
                )->count(),

                'totalLowStockItems' => InventoryItem::all()
                    ->filter(function ($item) {

                        return $item->available_quantity <= $item->minimum_stock;

                    })
                    ->count(),

                'totalWapdaBills' => Bill::count(),

                'totalReports' => $this->reportService->getReportCount(),

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

            'totalUsers' => User::where(
                'area_id',
                $user->area_id
            )->count(),

            /*
             * Areas
             */

            'totalAreas' => 1,

            /*
             * Inventory
             */

            'totalInventoryItems' => InventoryItem::where(
                'area_id',
                $user->area_id
            )->count(),

            /*
             * Assigned Items
             */

            'totalAssignedItems' => InventoryItem::where(
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

            'totalLowStockItems' => InventoryItem::where(
                'area_id',
                $user->area_id
            )
            ->get()
            ->filter(function ($item) {

                return $item->available_quantity <= $item->minimum_stock;

            })
            ->count(),

            /*
             * Bills uploaded by Manager
             */

            'totalWapdaBills' => Bill::where(
                'created_by',
                $user->id
            )->count(),

            /*
             * Reports
             */

            'totalReports' => $this->reportService->getReportCount(),

        ];
    }

    /**
     * Area Wise Solar vs WAPDA Comparison
     */
    private function getAreaComparisons(): array
    {
        $user = Auth::user();

        /*
        |--------------------------------------------------------------------------
        | Areas
        |--------------------------------------------------------------------------
        */

        $areaQuery = Area::query()
            ->select([
                'id',
                'area_name',
                'status',
            ])
            ->orderBy('area_name');

        /*
        |--------------------------------------------------------------------------
        | Manager
        |--------------------------------------------------------------------------
        */

        if ($user->role === 'Manager') {

            $areaQuery->where(
                'id',
                $user->area_id
            );

        }

        $areas = $areaQuery->get();

        /*
        |--------------------------------------------------------------------------
        | Bills
        |--------------------------------------------------------------------------
        */

        $billQuery = Bill::query()
            ->whereIn(
                'area_id',
                $areas->pluck('id')
            )
            ->orderByDesc('bill_year')
            ->orderByDesc('bill_month')
            ->orderByDesc('id');

        /*
        |--------------------------------------------------------------------------
        | Manager Bills
        |--------------------------------------------------------------------------
        */

        if ($user->role === 'Manager') {

            $billQuery->where(
                'created_by',
                $user->id
            );

        }

        $latestBills = $billQuery
            ->get()
            ->groupBy('area_id');

        /*
        |--------------------------------------------------------------------------
        | Build Comparison Data
        |--------------------------------------------------------------------------
        */

        return $areas->map(function ($area) use ($latestBills) {

            $bill = $latestBills
                ->get($area->id)
                ?->first();

            /*
            |--------------------------------------------------------------------------
            | Area Without Bill
            |--------------------------------------------------------------------------
            */

            if (!$bill) {

                return [

                    'area' => [

                        'id' => $area->id,

                        'name' => $area->area_name,

                        'status' => $area->status,

                    ],

                    'has_bill' => false,

                    'bill' => null,

                    'analysis' => null,

                    'comparison' => null,

                ];

            }

            /*
            |--------------------------------------------------------------------------
            | Bill Values
            |--------------------------------------------------------------------------
            */

            $unitsConsumed = (float) $bill->units_consumed;

            $generatedUnits = (float) $bill->generated_units;

            $billAmount = (float) $bill->bill_amount;

            /*
            |--------------------------------------------------------------------------
            | Existing Bill Analysis Service
            |--------------------------------------------------------------------------
            */

            $analysis = $this->billAnalysisService->analyze(

                $unitsConsumed,

                $generatedUnits,

                $billAmount

            );

            $analysisData = $analysis['analysis'];

            /*
            |--------------------------------------------------------------------------
            | Signed Difference
            |--------------------------------------------------------------------------
            |
            | Positive = Solar Generation is higher
            | Negative = WAPDA Consumption is higher
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

            if ($signedDifference >= 0) {

                $result = 'Benefit';

                $benefitUnits = $signedDifference;

                $lossUnits = 0;

            } else {

                $result = 'Loss';

                $benefitUnits = 0;

                $lossUnits = abs($signedDifference);

            }

            /*
            |--------------------------------------------------------------------------
            | Final Response
            |--------------------------------------------------------------------------
            */

            return [

                'area' => [

                    'id' => $area->id,

                    'name' => $area->area_name,

                    'status' => $area->status,

                ],

                'has_bill' => true,

                'bill' => [

                    'id' => $bill->id,

                    'month' => $bill->bill_month,

                    'year' => $bill->bill_year,

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

        })->values()->toArray();
    }

    /**
     * Solar Production Chart
     */
    private function getProductionChart(): array
    {
        $user = Auth::user();

        $query = Bill::query();

        /*
        |--------------------------------------------------------------------------
        | Manager
        |--------------------------------------------------------------------------
        */

        if ($user->role === 'Manager') {

            $query->where(
                'created_by',
                $user->id
            );

        }

        $rows = $query
            ->selectRaw("
                bill_month,
                COALESCE(SUM(generated_units),0) as production
            ")
            ->groupBy('bill_month')
            ->orderBy('bill_month')
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

        foreach ($months as $number => $name) {

            $production = optional(
                $rows->firstWhere(
                    'bill_month',
                    $number
                )
            )->production ?? 0;

            $chart[] = [

                'month' => $name,

                'production' => (float) $production,

            ];

        }

        return $chart;
    }

    /**
     * Battery Health Chart
     */
    private function getBatteryHealthChart(): array
    {
        $user = Auth::user();

        $query = InventoryItem::query()
            ->where(
                'item_type',
                'battery'
            );

        /*
        |--------------------------------------------------------------------------
        | Manager
        |--------------------------------------------------------------------------
        */

        if ($user->role === 'Manager') {

            $query->where(
                'area_id',
                $user->area_id
            );

        }

        $batteries = $query
            ->orderBy('item_name')
            ->get();

        $chart = [];

        foreach ($batteries as $battery) {

            $chart[] = [

                'battery' => $battery->item_name,

                'health' => (float) $battery->battery_health,

            ];

        }

        return $chart;
    }

    /**
     * WAPDA Bill Trend
     */
    private function getWapdaBillTrend(): array
    {
        $user = Auth::user();

        $query = Bill::query();

        /*
        |--------------------------------------------------------------------------
        | Manager
        |--------------------------------------------------------------------------
        */

        if ($user->role === 'Manager') {

            $query->where(
                'created_by',
                $user->id
            );

        }

        $rows = $query
            ->selectRaw("
                bill_month,
                COALESCE(SUM(bill_amount),0) as total_bill
            ")
            ->groupBy('bill_month')
            ->orderBy('bill_month')
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

        foreach ($months as $number => $name) {

            $bill = optional(

                $rows->firstWhere(
                    'bill_month',
                    $number
                )

            )->total_bill ?? 0;

            $chart[] = [

                'month' => $name,

                'bill' => (float) $bill,

            ];

        }

        return $chart;
    }

    /**
     * AI Performance
     */
    private function getAiPerformance(): array
    {
        $user = Auth::user();

        $query = Bill::query();

        /*
        |--------------------------------------------------------------------------
        | Manager
        |--------------------------------------------------------------------------
        */

        if ($user->role === 'Manager') {

            $query->where(
                'created_by',
                $user->id
            );

        }

        $bills = $query->get();

        $excellent = 0;

        $good = 0;

        $average = 0;

        $poor = 0;

        foreach ($bills as $bill) {

            $confidence = (float) $bill->ocr_confidence;

            if ($confidence >= 95) {

                $excellent++;

            } elseif ($confidence >= 85) {

                $good++;

            } elseif ($confidence >= 70) {

                $average++;

            } else {

                $poor++;

            }

        }

        return [

            [

                'name' => 'Excellent',

                'value' => $excellent,

            ],

            [

                'name' => 'Good',

                'value' => $good,

            ],

            [

                'name' => 'Average',

                'value' => $average,

            ],

            [

                'name' => 'Poor',

                'value' => $poor,

            ],

        ];
    }
}