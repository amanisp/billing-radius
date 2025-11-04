<?php

use App\Http\Controllers\Api\AreaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AuthMember;
use App\Http\Controllers\Api\ConnectionController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\OpticalController;
use App\Http\Controllers\Api\PayoutController;
use App\Http\Controllers\Api\ProfileController;
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
            Route::get('/me', [AuthController::class, 'me']);

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
            // Profiles
            Route::get('/profiles', [ProfileController::class, 'index']);
            Route::get('/profiles/list', [ProfileController::class, 'getList']);
            Route::post('/profiles', [ProfileController::class, 'store']);
            Route::put('/profiles/{id}', [ProfileController::class, 'update']);
            Route::delete('/profiles/{id}', [ProfileController::class, 'destroy']);

            //=== Connections ===
            Route::get('/connections', [ConnectionController::class, 'index']);
            Route::get('/connections/stats', [ConnectionController::class, 'stats']);
            Route::post('/connections', [ConnectionController::class, 'store']);
            Route::put('/connections/{id}', [ConnectionController::class, 'update']);
            Route::post('/connections/{id}/toggle-isolir', [ConnectionController::class, 'toggleIsolir']);
            Route::delete('/connections/{id}', [ConnectionController::class, 'destroy']);
            Route::get('/connections/{username}/sessions', [ConnectionController::class, 'getSessions']);
            //=== Members ===
            Route::get('/members', [MemberController::class, 'index']);
            Route::get('/members/stats', [MemberController::class, 'stats']);
            Route::get('/members/{id}', [MemberController::class, 'show']);
            Route::put('/members/{id}', [MemberController::class, 'update']);
            Route::put('/members/{id}/payment-detail', [MemberController::class, 'updatePaymentDetail']);
            Route::get('/members/{id}/invoices', [MemberController::class, 'getInvoices']);

            //=== Invoices ===
            Route::get('/invoices', [InvoiceController::class, 'index']);
            Route::get('/invoices/stats', [InvoiceController::class, 'stats']);
            Route::get('/invoices/date-range-stats', [InvoiceController::class, 'getDateRangeStats']);
            Route::post('/invoices/create', [InvoiceController::class, 'createInv']);
            Route::post('/invoices/generate-all', [InvoiceController::class, 'generateAll']);
            Route::post('/invoices/{id}/pay-manual', [InvoiceController::class, 'payManual']);
            Route::post('/invoices/{id}/cancel-payment', [InvoiceController::class, 'payCancel']);
            Route::delete('/invoices/{id}', [InvoiceController::class, 'destroy']);

            // ========== Payouts ==========
            Route::get('/payouts', [PayoutController::class, 'index']);
            Route::get('/payouts/stats', [PayoutController::class, 'stats']);
            Route::get('/payouts/{id}', [PayoutController::class, 'show']);
            Route::post('/payouts', [PayoutController::class, 'createPayout']);
            Route::post('/payouts/{id}/check-status', [PayoutController::class, 'checkStatus']);
            Route::delete('/payouts/{id}', [PayoutController::class, 'destroy']);
        }
    );
});
