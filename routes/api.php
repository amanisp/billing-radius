<?php

use App\Http\Controllers\Api\AuthMember;
use App\Http\Controllers\Api\WhatsAppApiController;
use App\Http\Controllers\WhatsappController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// WhatsApp API Routes
Route::post('/coba/send-message', [WhatsappController::class, 'testConnection']);
Route::post('/whatsapp/webhook/{groupId?}', [WhatsAppApiController::class, 'webhook']);


Route::prefix('v1')->group(function () {
    Route::prefix('auth-member')->group(function () {
        Route::post('/', [AuthMember::class, 'signIn']);
    });
});
