<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DocumentRecognitionController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\HealthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    Route::post('/recognize', [DocumentRecognitionController::class, 'recognize']);
    Route::get('/status/{task_id}', [DocumentRecognitionController::class, 'status']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/analytics', [AnalyticsController::class, 'getAnalytics']);
    });

    Route::get('/health', [HealthController::class, 'checkHealth']);
}); 