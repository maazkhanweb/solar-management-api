<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Analysis\BillAnalysisService;
use App\Services\DashboardService;
use App\Services\ReportService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    public function test_administrator_dashboard_uses_one_query_and_preserves_latest_bill_per_area(): void
    {
        $user = $this->user(1, 'Administrator', null);

        $result = $this->dashboard($user, [
            'total_users' => 4,
            'total_areas' => 2,
            'total_inventory_items' => 8,
            'total_assigned_items' => 3,
            'total_low_stock_items' => 1,
            'total_wapda_bills' => 4,
        ], [
            $this->bill(30, 1, 2, 2026, 100, 120, 1000, 1, 'North'),
            $this->bill(25, 2, 2, 2026, 80, 60, 800, 2, 'South'),
            $this->bill(20, 1, 2, 2026, 90, 90, 900, 1, 'North'),
            $this->bill(19, 3, 1, 2026, 50, 60, 500, null, null),
        ], function (string $sql, array $bindings): void {
            $this->assertStringContainsString('WITH statistics AS', $sql);
            $this->assertStringContainsString('LEFT JOIN areas ON bills.area_id = areas.id', $sql);
            $this->assertStringContainsString('ORDER BY bills.bill_year DESC, bills.bill_month DESC, bills.id DESC', $sql);
            $this->assertSame([], $bindings);
        });

        $this->assertSame([
            'totalUsers' => 4,
            'totalAreas' => 2,
            'totalInventoryItems' => 8,
            'totalAssignedItems' => 3,
            'totalLowStockItems' => 1,
            'totalWapdaBills' => 4,
            'totalReports' => 5,
        ], $result['statistics']);

        $this->assertCount(1, $result['comparisons']);
        $comparisonMonth = $result['comparisons'][0];
        $this->assertSame(2, $comparisonMonth['month']);
        $this->assertSame(2026, $comparisonMonth['year']);
        $this->assertSame(2, $comparisonMonth['comparison_count']);
        $this->assertSame([30, 25], array_map(fn ($comparison) => $comparison['bill']['id'], $comparisonMonth['comparisons']));
        $this->assertSame(30, $comparisonMonth['comparisons'][0]['bill']['id']);
        $this->assertSame('Benefit', $comparisonMonth['comparisons'][0]['comparison']['result']);
        $this->assertSame('Loss', $comparisonMonth['comparisons'][1]['comparison']['result']);
    }

    public function test_manager_dashboard_uses_one_query_with_existing_area_and_creator_scopes(): void
    {
        $user = $this->user(7, 'Manager', 12);

        $result = $this->dashboard($user, [
            'total_users' => 2,
            'total_areas' => 1,
            'total_inventory_items' => 5,
            'total_assigned_items' => 2,
            'total_low_stock_items' => 1,
            'total_wapda_bills' => 1,
        ], [
            $this->bill(41, 12, 9, 2026, 40, 80, 400, 12, 'Manager Area'),
        ], function (string $sql, array $bindings): void {
            $this->assertStringContainsString('users WHERE area_id IS NOT DISTINCT FROM ?', $sql);
            $this->assertStringContainsString('inventory_items', $sql);
            $this->assertStringContainsString('WHERE bills.created_by = ?', $sql);
            $this->assertSame([12, 7, 12, 7], $bindings);
        });

        $this->assertSame(1, $result['statistics']['totalAreas']);
        $this->assertSame(1, $result['statistics']['totalWapdaBills']);
        $this->assertSame(41, $result['comparisons'][0]['comparisons'][0]['bill']['id']);
        $this->assertSame('Manager Area', $result['comparisons'][0]['comparisons'][0]['area']['name']);
    }

    public function test_empty_bills_preserves_statistics_and_returns_an_empty_comparison_list(): void
    {
        $user = $this->user(7, 'Manager', 12);

        $result = $this->dashboard($user, [
            'total_users' => 0,
            'total_areas' => 1,
            'total_inventory_items' => 0,
            'total_assigned_items' => 0,
            'total_low_stock_items' => 0,
            'total_wapda_bills' => 0,
        ], []);

        $this->assertSame(0, $result['statistics']['totalWapdaBills']);
        $this->assertSame([], $result['comparisons']);
    }

    private function dashboard(User $user, array $statistics, array $bills, ?callable $assertQuery = null): array
    {
        Auth::shouldReceive('user')->once()->andReturn($user);

        DB::shouldReceive('selectOne')
            ->once()
            ->withArgs(function (string $sql, array $bindings = []) use ($assertQuery): bool {
                if ($assertQuery) {
                    $assertQuery($sql, $bindings);
                }

                return true;
            })
            ->andReturn((object) [
                'statistics' => json_encode($statistics),
                'bills' => json_encode($bills),
            ]);

        return (new DashboardService(
            new ReportService(),
            new BillAnalysisService()
        ))->getDashboardData();
    }

    private function user(int $id, string $role, ?int $areaId): User
    {
        $user = new User();
        $user->id = $id;
        $user->role = $role;
        $user->area_id = $areaId;

        return $user;
    }

    private function bill(
        int $id,
        int $areaId,
        int $month,
        int $year,
        float $consumed,
        float $generated,
        float $amount,
        ?int $dashboardAreaId,
        ?string $areaName
    ): array {
        return [
            'id' => $id,
            'area_id' => $areaId,
            'bill_month' => $month,
            'bill_year' => $year,
            'units_consumed' => $consumed,
            'generated_units' => $generated,
            'bill_amount' => $amount,
            'dashboard_area_id' => $dashboardAreaId,
            'dashboard_area_name' => $areaName,
            'dashboard_area_status' => $dashboardAreaId === null ? null : 'Active',
        ];
    }
}
