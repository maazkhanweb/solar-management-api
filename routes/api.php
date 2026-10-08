<?php

use App\Http\Controllers\Api\BillAnalysisController;
use App\Http\Controllers\Api\BillController;
use App\Http\Controllers\Api\BillOCRController;
use App\Http\Controllers\Api\ComparisonBillController;
use App\Http\Controllers\Api\ComparisonBillOCRController;
use App\Http\Controllers\Api\InventoryAssignmentController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\InventoryTransactionController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

Route::post(
    '/login',
    [AuthController::class, 'login']
);


/*
|--------------------------------------------------------------------------
| Warmup
|--------------------------------------------------------------------------
*/

Route::get('/warmup', function () {

    \DB::select('SELECT 1');

    return response()->json([
        'success' => true,
        'status' => 'warm',
    ]);

});


/*
|--------------------------------------------------------------------------
| EXISTING WAPDA OCR
|--------------------------------------------------------------------------
|
| DO NOT MODIFY.
|
*/

Route::post(
    '/bills/process-ocr',
    [BillOCRController::class, 'process']
);

Route::post(
    '/bills/process-solar-ocr',
    [BillOCRController::class, 'processSolar']
);


/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/logout',
        [AuthController::class, 'logout']
    );

    Route::get(
        '/me',
        [AuthController::class, 'me']
    );


    /*
    |--------------------------------------------------------------------------
    | AC/DC COMPARISON OCR
    |--------------------------------------------------------------------------
    |
    | Completely separate from WAPDA OCR.
    |
    */

    Route::post(
        '/comparison/process-ocr',
        [ComparisonBillOCRController::class, 'process']
    );


    /*
    |--------------------------------------------------------------------------
    | AC/DC COMPARISON BILL RECORDS
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/comparison/bills',
        [ComparisonBillController::class, 'index']
    );

    Route::get(
        '/comparison/bills/{comparisonBill}',
        [ComparisonBillController::class, 'show']
    );

    /*
    | Admin only is enforced inside service.
    */

    Route::put(
        '/comparison/bills/{comparisonBill}',
        [ComparisonBillController::class, 'update']
    );

    /*
    |--------------------------------------------------------------------------
    | HOME ANALYSIS
    |--------------------------------------------------------------------------
    |
    | Saves Home Analysis on the SAME bill record.
    |
    */

    Route::post(
        '/comparison/bills/{comparisonBill}/home-analysis',
        [ComparisonBillController::class, 'homeAnalysis']
    );

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    |
    | Admin only is enforced inside service.
    |
    */

    Route::delete(
        '/comparison/bills/{comparisonBill}',
        [ComparisonBillController::class, 'destroy']
    );


    /*
    |--------------------------------------------------------------------------
    | EXISTING DASHBOARD
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/dashboard',
        [DashboardController::class, 'index']
    );


    /*
    |--------------------------------------------------------------------------
    | EXISTING USER MANAGEMENT
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/users/options',
        [UserController::class, 'options']
    );

    Route::get(
        '/users',
        [UserController::class, 'index']
    );

    Route::get(
        '/users/{user}',
        [UserController::class, 'show']
    );

    Route::post(
        '/users',
        [UserController::class, 'store']
    );

    Route::put(
        '/users/{user}',
        [UserController::class, 'update']
    );

    Route::delete(
        '/users/{user}',
        [UserController::class, 'destroy']
    );


    /*
    |--------------------------------------------------------------------------
    | EXISTING AREA MANAGEMENT
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/areas/options',
        [AreaController::class, 'options']
    );

    Route::get(
        '/areas',
        [AreaController::class, 'index']
    );

    Route::get(
        '/areas/{area}',
        [AreaController::class, 'show']
    );

    Route::post(
        '/areas',
        [AreaController::class, 'store']
    );

    Route::put(
        '/areas/{area}',
        [AreaController::class, 'update']
    );

    Route::delete(
        '/areas/{area}',
        [AreaController::class, 'destroy']
    );


    /*
    |--------------------------------------------------------------------------
    | EXISTING INVENTORY
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/inventory',
        [InventoryController::class, 'index']
    );

    Route::get(
        '/inventory/{inventory}',
        [InventoryController::class, 'show']
    );

    Route::post(
        '/inventory',
        [InventoryController::class, 'store']
    );

    Route::put(
        '/inventory/{inventory}',
        [InventoryController::class, 'update']
    );

    Route::delete(
        '/inventory/{inventory}',
        [InventoryController::class, 'destroy']
    );


    /*
    |--------------------------------------------------------------------------
    | EXISTING INVENTORY TRANSACTIONS
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/inventory-transactions',
        [InventoryTransactionController::class, 'index']
    );

    Route::get(
        '/inventory-transactions/{inventoryTransaction}',
        [InventoryTransactionController::class, 'show']
    );

    Route::post(
        '/inventory-transactions',
        [InventoryTransactionController::class, 'store']
    );

    Route::put(
        '/inventory-transactions/{inventoryTransaction}',
        [InventoryTransactionController::class, 'update']
    );

    Route::delete(
        '/inventory-transactions/{inventoryTransaction}',
        [InventoryTransactionController::class, 'destroy']
    );


    /*
    |--------------------------------------------------------------------------
    | Existing Inventory Assignment
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/inventory-assignments',
        [InventoryAssignmentController::class, 'index']
    );

    Route::post(
        '/inventory-assignments',
        [InventoryAssignmentController::class, 'store']
    );

    Route::put(
        '/inventory-assignments/{inventoryAssignment}',
        [InventoryAssignmentController::class, 'update']
    );

    Route::delete(
        '/inventory-assignments/{inventoryAssignment}',
        [InventoryAssignmentController::class, 'destroy']
    );


    /*
    |--------------------------------------------------------------------------
    | Existing Bills
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/bills',
        [BillController::class, 'index']
    );

    Route::get(
        '/bills/{bill}',
        [BillController::class, 'show']
    );

    Route::post(
        '/bills',
        [BillController::class, 'store']
    );

    Route::put(
        '/bills/{bill}',
        [BillController::class, 'update']
    );

    Route::delete(
        '/bills/{bill}',
        [BillController::class, 'destroy']
    );


    /*
    |--------------------------------------------------------------------------
    | Existing Bill Analysis
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/bill-analysis',
        [BillAnalysisController::class, 'index']
    );


    /*
    |--------------------------------------------------------------------------
    | Existing Reports
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/reports',
        [ReportController::class, 'index']
    );

});