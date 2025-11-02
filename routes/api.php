<?php

use App\Http\Controllers\Api\AreaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AuthMember;
use App\Http\Controllers\Api\OpticalController;
use App\Http\Controllers\Api\WhatsAppApiController;
use App\Http\Controllers\WhatsappController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;



// WhatsApp API Routes
Route::post('/coba/send-message', [WhatsappController::class, 'testConnection']);
Route::post('/whatsapp/webhook/{groupId?}', [WhatsAppApiController::class, 'webhook']);


Route::post('/v1/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('v1')->group(
        function () {
            Route::post('/logout', [AuthController::class, 'logout']);

            //======Start Master Data======
            // Area
            Route::get('/areas', [AreaController::class, 'index']);
            Route::get('/areas/list', [AreaController::class, 'getAreaList']);
            Route::post('/areas', [AreaController::class, 'store']);
            Route::post('/areas/assign', [AreaController::class, 'assignTechnician']);
            Route::delete('/areas/{id}', [AreaController::class, 'destroy']);

            // Optical/ODP
            Route::get('/opticals', [OpticalController::class, 'index']);
            // Route::get('/areas/list', [AreaController::class, 'getAreaList']);
            // Route::post('/areas', [AreaController::class, 'store']);
            // Route::post('/areas/assign', [AreaController::class, 'assignTechnician']);
            // Route::delete('/areas/{id}', [AreaController::class, 'destroy']);

            //======End Master Data======
        }
    );
});
