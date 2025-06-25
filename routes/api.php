<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DocumentRecognitionController;
use App\Http\Controllers\Api\AnalyticsController;

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
    Route::post('/process_document', [DocumentRecognitionController::class, 'processDocument']);
    Route::get('/status/{document_id}', [DocumentRecognitionController::class, 'getStatus']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/analytics', [AnalyticsController::class, 'getAnalytics']);
    });
}); 