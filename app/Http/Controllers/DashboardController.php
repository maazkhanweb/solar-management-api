<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    protected DashboardService $dashboardService;

    public function __construct(
        DashboardService $dashboardService
    ) {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Dashboard Data
     */
    public function index(): JsonResponse
    {
        $dashboardData =
            $this->dashboardService->getDashboardData();

        return response()->json([

            'success' => true,

            'message' =>
                'Dashboard data fetched successfully.',

            'data' => $dashboardData,

        ]);
    }
}