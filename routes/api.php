<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\SmartHouse\ExpenseController;
use App\Http\Controllers\SmartHouse\SmartExpenseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::post('/auth/google-login', [AuthController::class, 'googleLogin']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware(['auth:api'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Developer full access
    Route::middleware(['role:developer'])->group(function () {
        // Route::apiResource('/projects', ProjectController::class);
        Route::get('/users', fn() => \App\Models\User::with('role', 'projects')->get());
        Route::post('/users', fn() => \App\Models\User::with('role', 'projects')->get());
    });

    // Other roles (only see their own projects)
    Route::middleware(['role:admin,team_lead,member,user'])->group(function () {
        Route::get('/smart/expenses', [ExpenseController::class, 'index']);
        Route::post('/smart/expenses', [ExpenseController::class, 'store']);
        // Overview endpoint
        Route::post('/smart/expenses/overview', [ExpenseController::class, 'overview']);
        Route::get('/smart/expenses/weekly', [ExpenseController::class, 'weeklySpending']);
        // AI suggestion
        Route::post('/smart/expenses/ai-suggestion', [SmartExpenseController::class, 'aiSuggestion']);


        Route::post('/smart/expenses/{id}', [ExpenseController::class, 'update']);
        Route::delete('/smart/expenses/{id}', [ExpenseController::class, 'destroy']);

    });
});
