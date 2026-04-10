<?php

use App\Http\Controllers\Analytic\AnalyticController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Bus\BusController;
use App\Http\Controllers\Bus\StopController;
use App\Http\Controllers\Bus\UserController;
use App\Http\Controllers\Business\BusinessController;
use App\Http\Controllers\SmartHouse\ExpenseController;
use App\Http\Controllers\SmartHouse\SmartExpenseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');
Route::get('/test', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Test endpoint is working!',
        'time' => now(),
    ]);
});
Route::post('/auth/google-login', [AuthController::class, 'googleLogin']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/update/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/update/reset-password', [AuthController::class, 'resetPassword']);

// busses app public for now
// Bus endpoints
Route::get('/buses', [BusController::class, 'index']);
Route::post('/buses/update-stop-index', [BusController::class, 'updateStopIndex']);
Route::get('/buses/group', [BusController::class, 'indexGroup']);
Route::get('/buses/{id}/tracking', [BusController::class, 'tracking']);
Route::post('/buses/{id}/trail', [BusController::class, 'deleteTrail']);
Route::get('/buses/{id}/location', [BusController::class, 'updateLocation']);
Route::get('/buses/{id}/history', [BusController::class, 'locationHistory']);



//bus admin route
Route::post('/auth/login-admin', [AuthController::class, 'bus_login']);
// Stop endpoints
Route::get('/stops', [StopController::class, 'index']);
Route::post('/stops', [StopController::class, 'store']);
// routes/api.php
Route::post('/stops/reorder', [StopController::class, 'reorder']);
Route::put('/stops/{id}', [StopController::class, 'update']);
Route::delete('/stops/{id}', [StopController::class, 'destroy']);

Route::middleware(['auth:api'])->group(function () {
    // Analytics
    Route::get('/analytics', [AnalyticController::class, 'index']);
    Route::get('/operator/analytics', [AnalyticController::class, 'operatorAnalytics']);
    
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Developer full access
    Route::middleware(['role:developer'])->group(function () {
        // Route::apiResource('/projects', ProjectController::class);
        Route::get('/users', fn() => \App\Models\User::with('role', 'projects')->get());
        Route::post('/users', fn() => \App\Models\User::with('role', 'projects')->get());
    });

    // Other roles (only see their own projects)
    Route::middleware(['role:admin,operator,team_lead,member,user'])->group(function () {
        // Route::get('/smart/expenses', [ExpenseController::class, 'index']);
        // Route::post('/smart/expenses', [ExpenseController::class, 'store']);
        // // Overview endpoint
        // Route::post('/smart/expenses/overview', [ExpenseController::class, 'overview']);
        // Route::get('/smart/expenses/weekly', [ExpenseController::class, 'weeklySpending']);
        // // AI suggestion
        // Route::post('/smart/expenses/ai-suggestion', [SmartExpenseController::class, 'aiSuggestion']);
        // Route::post('/smart/camera/damage-analyze', [SmartExpenseController::class, 'analyzeDamage']);


        // Route::post('/smart/expenses/{id}', [ExpenseController::class, 'update']);
        // Route::delete('/smart/expenses/{id}', [ExpenseController::class, 'destroy']);

        // update plate no to assign driver
        Route::post('/users/{id}', [UserController::class, 'updatePlateNo']);

        // bus registration
        Route::post('/buses', [BusController::class, 'store']);
        // Route::post('/bus-stops', [BusController::class, 'store']);

        Route::post('/buses/{id}', [BusController::class, 'update']);

        //bus user update info
        Route::post('/update/info', [AuthController::class, 'updateProfile']);

        //admin get users
        Route::get('/bta/users', [UserController::class, 'index']);
        Route::get('/bta/users/driver', [UserController::class, 'indexDriver']);
        Route::post('/bta/direct/password', [AuthController::class, 'directChangePassword']);

        // add businesses
        Route::get('/business', [BusinessController::class, 'index']);
        Route::post('/business', [BusinessController::class, 'store']);
        Route::put('/business/{id}', [BusinessController::class, 'update']);

    });
});
